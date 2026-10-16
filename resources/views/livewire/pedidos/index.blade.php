<?php

use App\Services\Erp\ConsultaDirectaErp;
use App\Services\ImportadorErp;
use App\Services\PedidosPorLiberarQuery;
use App\Support\Permisos;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithFileUploads, WithPagination;

    #[Url]
    public string $q = '';

    #[Url]
    public string $destino = '';

    #[Url]
    public string $estado = '';

    #[Url]
    public string $revision = '';

    public int $porPagina = 25;

    public $archivo;

    /** @var array<string,array{insertadas:int,actualizadas:int,desactivadas:int}>|null */
    public ?array $resumenImport = null;

    public function updated($campo): void
    {
        if (in_array($campo, ['q', 'destino', 'estado', 'revision', 'porPagina'], true)) {
            $this->resetPage();
        }
    }

    public function limpiarFiltros(): void
    {
        $this->reset('q', 'destino', 'estado', 'revision');
        $this->resetPage();
    }

    public function importar(ImportadorErp $importador): void
    {
        abort_unless(auth()->user()->can(Permisos::ERP_IMPORTAR), 403);

        $this->validate(['archivo' => ['required', 'file', 'extensions:xlsx,xls,xlsm', 'max:20480']]);

        $ruta = $this->archivo->store('importaciones');

        try {
            $this->resumenImport = $importador->importar(Storage::disk('local')->path($ruta));
        } catch (Throwable $e) {
            $this->addError('archivo', $e->getMessage());

            return;
        }

        Log::info('erp.importado', ['actor' => auth()->id(), 'resumen' => $this->resumenImport]);
        $this->reset('archivo');
        $this->resetPage();
        session()->flash('status', 'Importación completada.');
    }

    public function sincronizar(ConsultaDirectaErp $fuente): void
    {
        abort_unless(auth()->user()->can(Permisos::ERP_IMPORTAR), 403);

        try {
            $this->resumenImport = ['lineas_pedido' => $fuente->sincronizar()];
        } catch (Throwable $e) {
            $this->addError('archivo', $e->getMessage());

            return;
        }

        Log::info('erp.sincronizado', ['actor' => auth()->id(), 'resumen' => $this->resumenImport]);
        $this->resetPage();
        session()->flash('status', 'Sincronización con el ERP completada.');
    }

    public function with(PedidosPorLiberarQuery $consulta): array
    {
        $filtros = [
            'q' => $this->q, 'destino' => $this->destino,
            'estado' => $this->estado, 'revision' => $this->revision,
        ];

        return [
            'pedidos' => $consulta->resumenPorPedido($filtros, $this->porPagina),
            'destinos' => DB::table('clientes')->whereNotNull('destino')->orderBy('destino')->distinct()->pluck('destino'),
        ];
    }
}; ?>

<x-ui.page title="Pedidos por liberar"
           subtitle="Pedidos de venta de exportación en estado PL/PP pendientes de liberación.">
    <x-slot:actions>
        @can('erp.importar')
            @if (config('erp.fuente') === 'directo')
                <button wire:click="sincronizar" class="btn-dark" wire:loading.attr="disabled" wire:target="sincronizar">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" wire:loading.remove wire:target="sincronizar"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                    <span wire:loading.remove wire:target="sincronizar">Sincronizar ERP</span>
                    <span wire:loading wire:target="sincronizar">Sincronizando…</span>
                </button>
            @else
                <form wire:submit="importar" class="flex items-center gap-2">
                    <label class="btn-secondary cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" /></svg>
                        <span>{{ $archivo?->getClientOriginalName() ?? 'Elegir archivo' }}</span>
                        <input type="file" wire:model="archivo" accept=".xlsx,.xls,.xlsm" class="hidden">
                    </label>
                    <button class="btn-dark" wire:loading.attr="disabled" wire:target="importar,archivo" @disabled(! $archivo)>
                        <span wire:loading.remove wire:target="importar">Importar ERP</span>
                        <span wire:loading wire:target="importar">Importando…</span>
                    </button>
                </form>
            @endif
        @endcan
    </x-slot:actions>

    @error('archivo')
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">{{ $message }}</div>
    @enderror

    @if ($resumenImport)
        <div class="rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            <div class="font-medium">Importación completada</div>
            <div class="mt-1 flex flex-wrap gap-x-6 gap-y-1">
                @foreach ($resumenImport as $hoja => $r)
                    <span>{{ $hoja }}: <b>+{{ $r['insertadas'] }}</b> nuevas · {{ $r['actualizadas'] }} act. · {{ $r['desactivadas'] }} baja</span>
                @endforeach
            </div>
        </div>
    @endif

    <x-ui.card class="p-3">
        <div class="flex flex-wrap items-center gap-2">
            <input wire:model.live.debounce.400ms="q" placeholder="Buscar pedido o NIT" class="field-sm w-56">
            <select wire:model.live="destino" class="field-sm w-auto">
                <option value="">Destino: todos</option>
                @foreach ($destinos as $d) <option value="{{ $d }}">{{ $d }}</option> @endforeach
            </select>
            <select wire:model.live="estado" class="field-sm w-auto">
                <option value="">Estado: PL y PP</option>
                <option value="PL">PL</option>
                <option value="PP">PP</option>
            </select>
            <select wire:model.live="revision" class="field-sm w-auto">
                <option value="">Revisión: todas</option>
                <option value="REVISADO">Revisado</option>
                <option value="POR REVISAR">Por revisar</option>
            </select>
            <span class="ml-auto text-xs text-ink-400">{{ $pedidos->total() }} pedido(s)</span>
            <button wire:click="limpiarFiltros" class="btn-link">Limpiar</button>
        </div>
    </x-ui.card>

    <x-ui.card flush>
        <div class="max-h-[70vh] overflow-auto rounded-xl">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Pedido</th>
                    <th>Cliente</th>
                    <th>Destino</th>
                    <th>Fecha solicitud</th>
                    <th class="!text-right">Cantidad</th>
                    <th class="!text-right">Toneladas</th>
                    <th class="!text-right">Inventario</th>
                    <th class="!text-right">% Cumpl.</th>
                    <th>Estado</th>
                    <th>Fecha tent.</th>
                    <th>Seguimiento</th>
                    <th>Revisión</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($pedidos as $p)
                    <tr wire:key="ped-{{ $p->pedido }}">
                        <td>
                            <a href="{{ route('pedidos.show', $p->pedido) }}" wire:navigate class="font-semibold text-brand-600 hover:text-brand-500">{{ $p->pedido }}</a>
                        </td>
                        <td>
                            <div class="font-medium text-ink-900">{{ $p->nombre_cliente }}</div>
                            <div class="text-xs text-ink-400">{{ $p->nit_cliente }}</div>
                        </td>
                        <td class="whitespace-nowrap">
                            {{ $p->destino }}
                            @if ($p->destino === '#N/D')<x-ui.badge color="amber" class="ml-1">sin cliente</x-ui.badge>@endif
                        </td>
                        <td class="whitespace-nowrap">{{ $p->fecha_solicitud ? \Illuminate\Support\Carbon::parse($p->fecha_solicitud)->format('d/m/Y') : '—' }}</td>
                        <td class="num">{{ number_format((float) $p->cantidad_total, 0, ',', '.') }}</td>
                        <td class="num whitespace-nowrap">
                            {{ number_format((float) $p->toneladas_total, 3, ',', '.') }}
                            @if ($p->toneladas_incompleto)<x-ui.badge color="amber" class="ml-1">incompleto</x-ui.badge>@endif
                        </td>
                        <td class="num">{{ $p->inventario_total !== null ? number_format((float) $p->inventario_total, 0, ',', '.') : '—' }}</td>
                        <td class="num">
                            @if ($p->porcentaje_cumplimiento !== null)
                                <span @class([
                                    'font-medium',
                                    'text-emerald-600' => $p->porcentaje_cumplimiento >= 100,
                                    'text-amber-600' => $p->porcentaje_cumplimiento < 100,
                                ])>{{ number_format((float) $p->porcentaje_cumplimiento, 1, ',', '.') }}%</span>
                            @else — @endif
                        </td>
                        <td><x-ui.badge color="blue">{{ $p->estado }}</x-ui.badge></td>
                        <td class="whitespace-nowrap">{{ $p->fecha_tentativa_cargue ? \Illuminate\Support\Carbon::parse($p->fecha_tentativa_cargue)->format('d/m/Y') : '—' }}</td>
                        <td class="whitespace-nowrap">{{ $p->alerta }} <span class="text-ink-600">{{ $p->estado_seguimiento }}</span></td>
                        <td>
                            <x-ui.badge :color="$p->estado_revision === 'REVISADO' ? 'green' : 'gray'">{{ $p->estado_revision }}</x-ui.badge>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="12" class="!py-10 text-center text-ink-400">Sin pedidos por liberar.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <div>{{ $pedidos->links() }}</div>
</x-ui.page>
