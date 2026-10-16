<?php

namespace App\Mail;

use App\Services\CorreoCarguesBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CorreoCargues extends Mailable
{
    use Queueable, SerializesModels;

    /** @param  array<string,mixed>  $filtros */
    public function __construct(public array $filtros = []) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Cargues programados exportación');
    }

    public function content(): Content
    {
        $builder = new CorreoCarguesBuilder($this->filtros);

        return new Content(
            htmlString: '<html><body>'.$builder->html().'</body></html>',
        );
    }
}
