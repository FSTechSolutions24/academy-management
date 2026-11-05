<?php

namespace App\Http\Middleware;

use App\Support\BranchAccess;
use App\Support\BranchContext;
use App\Support\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBranch
{
    public function __construct(
        private readonly OrganizationContext $organizations,
        private readonly BranchContext $branches,
        private readonly BranchAccess $access,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $query = $this->access->query($request->user(), $this->organizations->get());
        $selectedId = $request->session()->get('active_branch_id');
        $branch = $selectedId ? (clone $query)->whereKey($selectedId)->first() : null;
        $branch ??= $query->orderByDesc('is_head_office')->orderBy('name_en')->first();

        abort_unless($branch, 403, 'No active centre workspace is available.');
        $request->session()->put('active_branch_id', $branch->id);
        $this->branches->set($branch);
        $request->attributes->set('branch', $branch);

        return $next($request);
    }
}
