<?php

namespace App\Notifications;

use Filament\Actions\Action;
use Filament\Notifications\Notification as NotifikasiFilament;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Dasar notifikasi aplikasi (02-ARSITEKTUR §10): antrean `notifikasi`, dikirim setelah commit, kanal database
 * (format lonceng Filament) + surel kecuali subkelas mengubah kanal(). Isi dihitung di konstruktor (teks biasa),
 * sehingga notifikasi tetap terkirim walau model pemicu (mis. relasi) sudah dihapus saat antrean diproses.
 * Penerima nonaktif (users.aktif = false) tidak pernah menerima apa pun.
 */
abstract class NotifikasiRegulasi extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    abstract public function judul(): string;

    abstract public function isi(): string;

    /**
     * URL tujuan tombol (halaman admin dokumen, atau halaman publik).
     */
    public function url(): ?string
    {
        return null;
    }

    public function labelTombol(): string
    {
        return 'Buka dokumen';
    }

    public function warna(): string
    {
        return 'info';
    }

    /**
     * Baris tambahan surel setelah isi (mis. catatan penolakan).
     *
     * @return list<string>
     */
    public function barisTambahan(): array
    {
        return [];
    }

    /**
     * @return list<string>
     */
    public function kanal(): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        if (isset($notifiable->aktif) && ! $notifiable->aktif) {
            return [];
        }

        return $this->kanal();
    }

    /**
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return ['database' => 'default', 'mail' => 'default'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $notifikasi = NotifikasiFilament::make()
            ->title($this->judul())
            ->body(collect([$this->isi(), ...$this->barisTambahan()])->implode("\n"))
            ->status($this->warna());

        if ($this->url() !== null) {
            $notifikasi->actions([
                Action::make('buka')->label($this->labelTombol())->url($this->url())->markAsRead(),
            ]);
        }

        return $notifikasi->getDatabaseMessage();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $pesan = (new MailMessage)
            ->subject('[Regulasi FKIP] '.$this->judul())
            ->greeting('Yth. Bapak/Ibu,')
            ->line($this->isi());

        foreach ($this->barisTambahan() as $baris) {
            $pesan->line($baris);
        }

        if ($this->url() !== null) {
            $pesan->action($this->labelTombol(), $this->url());
        }

        return $pesan->salutation('Regulasi FKIP Universitas Siliwangi');
    }

    /**
     * Antrean `default` dan hanya dikirim setelah transaksi pemanggil di-commit.
     */
    protected function siapkanAntrean(): void
    {
        $this->onQueue('default');
        $this->afterCommit();
    }
}
