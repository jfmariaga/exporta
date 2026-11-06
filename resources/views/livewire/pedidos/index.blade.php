<?php

use App\Services\Erp\ConsultaDirectaErp;
use App\Services\ImportadorErp;
use App\Services\PedidosPorLiberarQuery;
use App\Support\ConOrdenamiento;
use App\Support\ExportaExcel;
use App\Support\Permisos;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;

new #[Layout('layouts.app')] class extends Component
{
    use ConOrdenamiento, ExportaExcel, WithFileUploads, WithPagination;

    private const COLUMNAS_ORDENABLES = [
        'pedido', 'nombre_cliente', 'destino', 'fecha_solicitud', 'cantidad_total',
        'toneladas_total', 'cajas_total', 'volumen_total', 'inventario_total',
        'porcentaje_cumplimiento', 'estado', 'fecha_tentativa_cargue',
        'estado_seguimiento', 'revisado',
    ];

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

    private function filtros(): array
    {
        return [
            'q' => $this->q, 'destino' => $this->destino,
            'estado' => $this->estado, 'revision' => $this->revision,
        ];
    }

    public function exportar(PedidosPorLiberarQuery $consulta): StreamedResponse
    {
        $filas = $consulta->resumenPorPedidoColeccion(
            $this->filtros(), $this->campoOrdenValido(self::COLUMNAS_ORDENABLES), $this->ordenDir,
        );

        return $this->descargarExcel('pedidos-por-liberar.xlsx', [
            'Pedido', 'Cliente', 'NIT', 'Destino', 'F. solicitud', 'Cantidad', 'Toneladas', 'Cajas',
            'Volumen m3', 'Contenedor', 'Inventario', '% Cumpl.', 'Estado', 'F. tent.', 'Seguimiento', 'Revisión',
        ], $filas->map(fn ($p) => [
            $p->pedido, $p->nombre_cliente, $p->nit_cliente, $p->destino,
            $p->fecha_solicitud ? Carbon::parse($p->fecha_solicitud)->format('d/m/Y') : null,
            (float) $p->cantidad_total, round((float) $p->toneladas_total, 1), $p->cajas_total, $p->volumen_total,
            $p->contenedor_sugerido, $p->inventario_total !== null ? (float) $p->inventario_total : null,
            $p->porcentaje_cumplimiento !== null ? round((float) $p->porcentaje_cumplimiento, 1) : null,
            $p->estado, $p->fecha_tentativa_cargue ? Carbon::parse($p->fecha_tentativa_cargue)->format('d/m/Y') : null,
            $p->estado_seguimiento, $p->estado_revision,
        ]));
    }

    public function with(PedidosPorLiberarQuery $consulta): array
    {
        $filtros = $this->filtros();

        return [
            'pedidos' => $consulta->resumenPorPedido(
                $filtros, $this->porPagina,
                $this->campoOrdenValido(self::COLUMNAS_ORDENABLES), $this->ordenDir,
            ),
            'destinos' => DB::table('clientes')->whereNotNull('destino')->orderBy('destino')->distinct()->pluck('destino'),
        ];
    }
}; ?>

<x-ui.page title="Pedidos por liberar"
           subtitle="Pedidos de venta de exportación en estado PL/PP pendientes de liberación.">
    <x-slot:actions>
        <button wire:click="exportar" class="btn-secondary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
            Exportar
        </button>
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
        <div class="rounded-lg border border-danger/20 bg-danger-soft px-4 py-3 text-sm text-danger dark:border-danger-dark/20 dark:bg-danger-dark/10 dark:text-danger-dark">{{ $message }}</div>
    @enderror

    @if ($resumenImport)
        <div class="rounded-lg border border-line bg-cold px-4 py-3 text-sm text-ink-900 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-200">
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
            <span class="ml-auto text-xs text-ink-secondary dark:text-gray-400">{{ $pedidos->total() }} pedido(s)</span>
            <button wire:click="limpiarFiltros" class="btn-link">Limpiar</button>
        </div>
    </x-ui.card>

    <x-ui.card flush>
        <div class="max-h-[70vh] overflow-auto rounded-xl">
            <table class="data-table data-table--compact">
                <thead>
                <tr>
                    <x-ui.th-sort campo="pedido" :actual="$ordenCampo" :dir="$ordenDir">Pedido</x-ui.th-sort>
                    <x-ui.th-sort campo="nombre_cliente" :actual="$ordenCampo" :dir="$ordenDir">Cliente</x-ui.th-sort>
                    <x-ui.th-sort campo="destino" :actual="$ordenCampo" :dir="$ordenDir">Destino</x-ui.th-sort>
                    <x-ui.th-sort campo="fecha_solicitud" :actual="$ordenCampo" :dir="$ordenDir">F. solicitud</x-ui.th-sort>
                    <x-ui.th-sort campo="cantidad_total" :actual="$ordenCampo" :dir="$ordenDir" align="right">Cantidad</x-ui.th-sort>
                    <x-ui.th-sort campo="toneladas_total" :actual="$ordenCampo" :dir="$ordenDir" align="right">Toneladas</x-ui.th-sort>
                    <x-ui.th-sort campo="cajas_total" :actual="$ordenCampo" :dir="$ordenDir" align="right">Cajas</x-ui.th-sort>
                    <x-ui.th-sort campo="volumen_total" :actual="$ordenCampo" :dir="$ordenDir" align="right">Volumen m³</x-ui.th-sort>
                    <th>Contenedor</th>
                    <x-ui.th-sort campo="inventario_total" :actual="$ordenCampo" :dir="$ordenDir" align="right">Inventario</x-ui.th-sort>
                    <x-ui.th-sort campo="porcentaje_cumplimiento" :actual="$ordenCampo" :dir="$ordenDir" align="right">% Cumpl.</x-ui.th-sort>
                    <x-ui.th-sort campo="estado" :actual="$ordenCampo" :dir="$ordenDir">Estado</x-ui.th-sort>
                    <x-ui.th-sort campo="fecha_tentativa_cargue" :actual="$ordenCampo" :dir="$ordenDir">F. tent.</x-ui.th-sort>
                    <x-ui.th-sort campo="estado_seguimiento" :actual="$ordenCampo" :dir="$ordenDir">Seguimiento</x-ui.th-sort>
                    <x-ui.th-sort campo="revisado" :actual="$ordenCampo" :dir="$ordenDir">Revisión</x-ui.th-sort>
                </tr>
                </thead>
                <tbody>
                @forelse ($pedidos as $p)
                    <tr wire:key="ped-{{ $p->pedido }}">
                        <td>
                            <a href="{{ route('pedidos.show', $p->pedido) }}" wire:navigate class="font-semibold text-brand-600 hover:text-brand-500">{{ $p->pedido }}</a>
                        </td>
                        <td>
                            <div class="font-medium text-ink-900 dark:text-gray-100">{{ $p->nombre_cliente }}</div>
                            <div class="text-xs text-ink-secondary dark:text-gray-500">{{ $p->nit_cliente }}</div>
                        </td>
                        <td class="whitespace-nowrap">
                            {{ $p->destino }}
                            @if ($p->destino === '#N/D')<x-ui.badge color="amber" class="ml-1">sin cliente</x-ui.badge>@endif
                        </td>
                        <td class="whitespace-nowrap">{{ $p->fecha_solicitud ? \Illuminate\Support\Carbon::parse($p->fecha_solicitud)->format('d/m/Y') : '—' }}</td>
                        <td class="num">{{ number_format((float) $p->cantidad_total, 0, ',', '.') }}</td>
                        <td class="num whitespace-nowrap">
                            {{ number_format((float) $p->toneladas_total, 1, ',', '.') }}
                            @if ($p->toneladas_incompleto)<x-ui.badge color="amber" class="ml-1" title="Falta factor kg en algún ítem">!</x-ui.badge>@endif
                        </td>
                        <td class="num whitespace-nowrap">
                            {{ number_format((float) $p->cajas_total, 1, ',', '.') }}
                            @if ($p->cajas_incompleto)<x-ui.badge color="amber" class="ml-1" title="Falta embalaje en algún ítem">!</x-ui.badge>@endif
                        </td>
                        <td class="num whitespace-nowrap">
                            {{ number_format((float) $p->volumen_total, 2, ',', '.') }}
                            @if ($p->volumen_incompleto)<x-ui.badge color="amber" class="ml-1" title="Falta embalaje o volumen en algún ítem">!</x-ui.badge>@endif
                        </td>
                        <td class="whitespace-nowrap">
                            @if ($p->contenedor_sugerido === 'Excede cubicaje')
                                <x-ui.badge color="red" title="Excede cubicaje">Excede</x-ui.badge>
                            @elseif ($p->contenedor_sugerido)
                                {{ str_replace('Contenedor ', '', $p->contenedor_sugerido) }}
                            @else — @endif
                        </td>
                        <td class="num">{{ $p->inventario_total !== null ? number_format((float) $p->inventario_total, 0, ',', '.') : '—' }}</td>
                        <td class="num">
                            @if ($p->porcentaje_cumplimiento !== null)
                                <span @class([
                                    'font-medium',
                                    'text-success dark:text-success-dark' => $p->porcentaje_cumplimiento >= 100,
                                    'text-warning-text dark:text-warning' => $p->porcentaje_cumplimiento < 100,
                                ])>{{ number_format((float) $p->porcentaje_cumplimiento, 1, ',', '.') }}%</span>
                            @else — @endif
                        </td>
                        <td><x-ui.badge color="blue">{{ $p->estado }}</x-ui.badge></td>
                        <td class="whitespace-nowrap">{{ $p->fecha_tentativa_cargue ? \Illuminate\Support\Carbon::parse($p->fecha_tentativa_cargue)->format('d/m/Y') : '—' }}</td>
                        <td class="whitespace-nowrap">{{ $p->alerta }} <span class="text-ink-900 dark:text-gray-300">{{ $p->estado_seguimiento }}</span></td>
                        <td>
                            <x-ui.badge :color="$p->estado_revision === 'REVISADO' ? 'green' : 'gray'">{{ $p->estado_revision }}</x-ui.badge>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="15" class="!py-10 text-center text-ink-secondary dark:text-gray-500">Sin pedidos por liberar.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <div>{{ $pedidos->links() }}</div>
</x-ui.page>
