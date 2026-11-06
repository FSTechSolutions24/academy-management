<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

final class StudentImportFileReader
{
    public const MAX_ROWS = 5000;

    public const REQUIRED_HEADERS = ['name_ar', 'guardian_name', 'guardian_phone'];

    public const ALLOWED_HEADERS = [
        'name_ar', 'name_en', 'date_of_birth', 'gender', 'phone', 'email',
        'guardian_name', 'guardian_phone', 'guardian_email', 'guardian_relationship', 'notes',
    ];

    public function read(UploadedFile $file): array
    {
        return match (strtolower($file->getClientOriginalExtension())) {
            'csv' => $this->readCsv($file->getRealPath()),
            'xlsx' => $this->readXlsx($file->getRealPath()),
            default => throw new RuntimeException('Upload a CSV or XLSX file.'),
        };
    }

    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new RuntimeException('The uploaded file could not be read.');
        }

        try {
            $header = fgetcsv($handle, null, ',', '"', '');
            if (! is_array($header)) {
                throw new RuntimeException('The file is empty.');
            }
            $rows = [];
            $rowNumber = 1;
            while (($values = fgetcsv($handle, null, ',', '"', '')) !== false) {
                $rowNumber++;
                if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw new RuntimeException('A file can contain at most 5,000 student rows.');
                }
                $rows[] = [$rowNumber, $this->combine($header, $values)];
            }

            return $this->validateHeaders($header, $rows);
        } finally {
            fclose($handle);
        }
    }

    private function readXlsx(string $path): array
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('XLSX import is unavailable because the PHP ZIP extension is not enabled.');
        }
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('The XLSX file could not be opened.');
        }

        try {
            if ($zip->numFiles > 100) {
                throw new RuntimeException('The XLSX file contains too many internal files.');
            }
            $expandedBytes = 0;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                $expandedBytes += (int) ($stat['size'] ?? 0);
                if ($expandedBytes > 20 * 1024 * 1024) {
                    throw new RuntimeException('The uncompressed XLSX file is too large.');
                }
            }

            $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
            if ($sheetXml === false) {
                throw new RuntimeException('The first worksheet could not be read.');
            }
            $sheet = $this->xml($sheetXml);
            $sharedStrings = [];
            $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
            if ($sharedXml !== false) {
                foreach ($this->xml($sharedXml)->si as $item) {
                    $sharedStrings[] = implode('', array_map('strval', $item->xpath('.//*[local-name()="t"]') ?: []));
                }
            }

            $rows = [];
            foreach ($sheet->sheetData->row ?? [] as $rowIndex => $row) {
                $rowNumber = (int) ($row['r'] ?? ($rowIndex + 1));
                if ($rowNumber === 1) {
                    $header = $this->xlsxRow($row, $sharedStrings);
                    continue;
                }
                $values = $this->xlsxRow($row, $sharedStrings);
                if (count(array_filter($values, fn ($value) => trim((string) $value) !== '')) === 0) {
                    continue;
                }
                if (count($rows) >= self::MAX_ROWS) {
                    throw new RuntimeException('A file can contain at most 5,000 student rows.');
                }
                $rows[] = [$rowNumber, $this->combine($header ?? [], $values)];
            }

            if (! isset($header)) {
                throw new RuntimeException('The first worksheet is empty.');
            }

            return $this->validateHeaders($header, $rows);
        } finally {
            $zip->close();
        }
    }

    private function xlsxRow(SimpleXMLElement $row, array $sharedStrings): array
    {
        $values = [];
        foreach ($row->c as $cell) {
            if (isset($cell->f)) {
                throw new RuntimeException('Formula cells are not accepted in student import files.');
            }
            preg_match('/^([A-Z]+)/', (string) $cell['r'], $match);
            $column = 0;
            foreach (str_split($match[1] ?? '') as $letter) {
                $column = ($column * 26) + ord($letter) - 64;
            }
            $index = max(0, $column - 1);
            $value = (string) ($cell->v ?? '');
            if ((string) $cell['t'] === 's') {
                $value = $sharedStrings[(int) $value] ?? '';
            } elseif ((string) $cell['t'] === 'inlineStr') {
                $value = implode('', array_map('strval', $cell->is->xpath('.//*[local-name()="t"]') ?: []));
            }
            $values[$index] = $value;
        }
        if ($values === []) {
            return [];
        }
        ksort($values);

        $lastColumn = max(array_keys($values));
        for ($index = 0; $index <= $lastColumn; $index++) {
            $values[$index] ??= '';
        }
        ksort($values);

        return array_values($values);
    }

    private function xml(string $content): SimpleXMLElement
    {
        $xml = simplexml_load_string($content, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOBLANKS);
        if (! $xml instanceof SimpleXMLElement) {
            throw new RuntimeException('The XLSX worksheet contains invalid XML.');
        }

        return $xml;
    }

    private function combine(array $header, array $values): array
    {
        $cleanHeaders = array_map(fn ($value) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $value))), $header);
        $normalized = array_fill_keys(self::ALLOWED_HEADERS, '');
        foreach ($cleanHeaders as $index => $key) {
            if (in_array($key, self::ALLOWED_HEADERS, true)) {
                $value = trim((string) ($values[$index] ?? ''));
                if ($key === 'date_of_birth' && is_numeric($value) && (float) $value >= 18000 && (float) $value <= 70000) {
                    $days = (int) floor((float) $value);
                    $value = (new \DateTimeImmutable('1899-12-30'))->modify("+{$days} days")->format('Y-m-d');
                }
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function validateHeaders(array $header, array $rows): array
    {
        $cleanHeaders = array_map(fn ($value) => strtolower(trim((string) preg_replace('/^\xEF\xBB\xBF/', '', (string) $value))), $header);
        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $cleanHeaders));
        if ($missing !== []) {
            throw new RuntimeException('Missing required columns: '.implode(', ', $missing).'.');
        }
        if (count(array_unique($cleanHeaders)) !== count($cleanHeaders)) {
            throw new RuntimeException('Column names must be unique.');
        }
        foreach ($cleanHeaders as $key) {
            if (! in_array($key, self::ALLOWED_HEADERS, true)) {
                throw new RuntimeException('Unsupported column: '.$key.'. Use the provided import template.');
            }
        }

        return $rows;
    }
}
