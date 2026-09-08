<?php

namespace App\Filament\Pages;

use App\Models\Branch;
use App\Models\CashSession;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Caja: apertura y cierre de turno, arqueo y movimientos de efectivo.
 * Una caja abierta por sucursal a la vez.
 */
class Caja extends Page
{
    protected string $view = 'filament.pages.caja';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $title = 'Caja';

    protected static ?string $navigationLabel = 'Caja';

    protected static ?int $navigationSort = 6;

    protected function branch(): ?Branch
    {
        return Branch::where('active', true)->orderBy('id')->first() ?? Branch::orderBy('id')->first();
    }

    /** Caja abierta de la sucursal actual, si existe. */
    public function current(): ?CashSession
    {
        $branch = $this->branch();
        if (! $branch) {
            return null;
        }

        return CashSession::where('branch_id', $branch->id)
            ->where('status', 'abierta')
            ->latest('opened_at')
            ->first();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('abrir')
                ->label('Abrir caja')
                ->icon(Heroicon::OutlinedLockOpen)
                ->visible(fn (): bool => $this->current() === null && $this->branch() !== null)
                ->form([
                    TextInput::make('opening_amount')
                        ->label('Fondo inicial')
                        ->numeric()->minValue(0)->default(0)->required()->prefix('S/'),
                ])
                ->action(fn (array $data) => $this->abrir((float) $data['opening_amount'])),

            Action::make('movimiento')
                ->label('Registrar movimiento')
                ->icon(Heroicon::OutlinedArrowsUpDown)
                ->visible(fn (): bool => $this->current() !== null)
                ->form([
                    Select::make('type')->label('Tipo')->options([
                        'entrada' => 'Entrada (ingreso de efectivo)',
                        'salida' => 'Salida (retiro/gasto)',
                    ])->required(),
                    TextInput::make('amount')->label('Monto')->numeric()->minValue(0.01)->required()->prefix('S/'),
                    TextInput::make('reason')->label('Motivo')->required()->maxLength(255),
                ])
                ->action(fn (array $data) => $this->movimiento($data['type'], (float) $data['amount'], $data['reason'])),

            Action::make('cerrar')
                ->label('Cerrar caja')
                ->icon(Heroicon::OutlinedLockClosed)
                ->color('danger')
                ->visible(fn (): bool => $this->current() !== null)
                ->form([
                    TextInput::make('closing_amount')
                        ->label('Efectivo contado')
                        ->helperText('Cuenta el efectivo físico en caja para el arqueo.')
                        ->numeric()->minValue(0)->required()->prefix('S/'),
                    Textarea::make('notes')->label('Observaciones')->rows(2),
                ])
                ->action(fn (array $data) => $this->cerrar((float) $data['closing_amount'], $data['notes'] ?? null)),
        ];
    }

    public function abrir(float $opening): void
    {
        $branch = $this->branch();

        if (! $branch) {
            Notification::make()->danger()->title('No hay sucursal configurada')->send();

            return;
        }

        if ($this->current()) {
            Notification::make()->warning()->title('Ya hay una caja abierta')->send();

            return;
        }

        CashSession::create([
            'tenant_id' => Filament::getTenant()->getKey(),
            'branch_id' => $branch->id,
            'user_id' => auth()->id(),
            'status' => 'abierta',
            'opening_amount' => $opening,
            'opened_at' => now(),
        ]);

        Notification::make()->success()->title('Caja abierta')->send();
    }

    public function movimiento(string $type, float $amount, string $reason): void
    {
        $session = $this->current();
        if (! $session || ! in_array($type, ['entrada', 'salida'], true)) {
            return;
        }

        $session->movements()->create([
            'tenant_id' => $session->tenant_id,
            'user_id' => auth()->id(),
            'type' => $type,
            'amount' => $amount,
            'reason' => $reason,
        ]);

        Notification::make()->success()->title('Movimiento registrado')->send();
    }

    public function cerrar(float $counted, ?string $notes): void
    {
        $session = $this->current();
        if (! $session) {
            return;
        }

        $expected = $session->expectedCash();

        $session->update([
            'status' => 'cerrada',
            'closing_amount' => $counted,
            'expected_amount' => $expected,
            'difference' => round($counted - $expected, 2),
            'closed_at' => now(),
            'notes' => $notes,
        ]);

        Notification::make()->success()
            ->title('Caja cerrada')
            ->body('Esperado: S/ ' . number_format($expected, 2) . ' · Contado: S/ ' . number_format($counted, 2))
            ->send();
    }
}
