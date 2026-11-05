<?php
namespace App\Mail;
use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
class StaffInvitationMail extends Mailable
{
    use Queueable, SerializesModels;
    public function __construct(public OrganizationInvitation $invitation, public string $acceptUrl) {}
    public function build(): self { return $this->subject('You are invited to join '.$this->invitation->organization->name_ar)->view('emails.staff-invitation'); }
}
