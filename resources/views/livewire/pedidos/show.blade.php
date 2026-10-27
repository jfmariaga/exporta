<?php

use App\Enums\EstadoMateriales;
use App\Enums\EstadoSeguimiento;
use App\Models\LineaPedido;
use App\Models\Seguimiento;
use App\Models\SeguimientoPedido;
use App\Services\Inventario\DisponibilidadInventario;
use App\Services\PedidosPorLiberarQuery;
use App\Support\EstadosPedido;
use App\Support\Permisos;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public string $pedido;

    // Seguimiento a nivel de pedido
    public string $estadoSeguimiento = 'Pendiente';

    public ?string $fechaTentativa = null;

    // Seguimiento a nivel de línea:
    // [item => ['estado_materiales'=>..,'observaciones'=>..,'fecha_programa'=>..,'inventario'=>..]]
    public array $lineasSeg = [];

    // Filtro del detalle por parámetro (categoría de la hoja Parametros)
    #[Url]
    public string $categoria = '';

    public int $porPagina = 15;

    public function updatedCategoria(): void
    {
        $this->resetPage();
    }

    public function mount(string $pedido): void
    {
        $this->pedido = $pedido;

        abort_if(app(PedidosPorLiberarQuery::class)->detallePedido($pedido) === null, 404);

        $sp = SeguimientoPedido::where('pedido', $pedido)->first();
        $this->estadoSeguimiento = $sp?->estado_seguimiento?->value ?? 'Pendiente';
        $this->fechaTentativa = $sp?->fecha_tentativa_cargue?->format('Y-m-d');

        foreach (Seguimiento::where('pedido', $pedido)->get() as $s) {
            $this->lineasSeg[$s->item] = [
                'estado_materiales' => $s->estado_materiales,
                'observaciones' => $s->observaciones,
                'fecha_programa' => $s->fecha_programa?->format('Y-m-d'),
                'inventario' => $s->inventario !== null ? (string) $s->inventario : null,
            ];
        }
    }

    public function guardarPedido(): void
    {
        abort_unless(auth()->user()->can(Permisos::SEGUIMIENTO_EDITAR), 403);

        $this->validate([
            'estadoSeguimiento' => ['required', Rule::in(EstadoSeguimiento::valores())],
            'fechaTentativa' => ['nullable', 'date'],
        ]);

        SeguimientoPedido::updateOrCreate(
            ['pedido' => $this->pedido],
            [
                'estado_seguimiento' => $this->estadoSeguimiento,
                'fecha_tentativa_cargue' => $this->fechaTentativa ?: null,
                'actualizado_por' => auth()->id(),
            ],
        );

        session()->flash('status', 'Seguimiento del pedido guardado.');
    }

    public function guardarLineas(DisponibilidadInventario $disponibilidad): void
    {
        abort_unless(auth()->user()->can(Permisos::SEGUIMIENTO_EDITAR), 403);

        $this->validate([
            'lineasSeg.*.estado_materiales' => ['nullable', Rule::in(EstadoMateriales::valores())],
            'lineasSeg.*.observaciones' => ['nullable', 'string'],
            'lineasSeg.*.fecha_programa' => ['nullable', 'date'],
            'lineasSeg.*.inventario' => [
                'nullable', 'numeric', 'min:0',
                function (string $attribute, mixed $value, Closure $fail) use ($disponibilidad) {
                    if ($value === null || $value === '') {
                        return;
                    }
                    $item = explode('.', $attribute)[1];
                    $restante = $disponibilidad->disponible($item, $this->pedido);
                    if ((float) $value > $restante) {
                        $fail('Disponible para el ítem '.$item.': '.number_format($restante, 3, ',', '.').
                            '. El valor ingresado excede el inventario sincronizado.');
                    }
                },
            ],
        ]);

        $validos = LineaPedido::where('pedido', $this->pedido)
            ->where('activo', true)
            ->whereIn('estado', EstadosPedido::POR_LIBERAR)
            ->get()->keyBy('item');

        DB::transaction(function () use ($validos) {
            foreach ($this->lineasSeg as $item => $campos) {
                $linea = $validos->get((string) $item);
                if (! $linea) {
                    continue;
                }
                Seguimiento::updateOrCreate(
                    ['pedido' => $this->pedido, 'item' => (string) $item],
                    [
                        'descripcion' => $linea->descripcion,
                        'cantidad' => $linea->cantidad_pedida,
                        'estado_materiales' => ($campos['estado_materiales'] ?? null) ?: null,
                        'observaciones' => $campos['observaciones'] ?? null,
                        'fecha_programa' => ($campos['fecha_programa'] ?? null) ?: null,
                        'inventario' => ($campos['inventario'] ?? null) === null || $campos['inventario'] === ''
                            ? null : $campos['inventario'],
                        'actualizado_por' => auth()->id(),
                    ],
                );
            }
        });

        session()->flash('status', 'Seguimiento de líneas guardado.');
    }

    public function with(PedidosPorLiberarQuery $consulta, DisponibilidadInventario $disponibilidad): array
    {
        $detalle = $consulta->detallePedido($this->pedido);
        $todas = $detalle['lineas'];

        $categorias = $todas->pluck('categoria')->filter()->unique()->sort()->values();

        $lineas = $this->categoria === ''
            ? $todas
            : $todas->where('categoria', $this->categoria)->values();

        $paginaActual = Paginator::resolveCurrentPage() ?: 1;
        $pagina = $lineas->forPage($paginaActual, $this->porPagina)->values();
        $paginador = new LengthAwarePaginator(
            $pagina, $lineas->count(), $this->porPagina, $paginaActual,
            ['path' => Paginator::resolveCurrentPath()],
        );

        // Remanente GLOBAL del ítem (sin excluir este pedido): lo que queda
        // libre en todo el pool, para que el usuario vea que al asignar aquí
        // el total se consume. El tope de validación al guardar sí excluye
        // este pedido (para no bloquear la edición de su propia línea).
        $disponibles = $pagina->mapWithKeys(
            fn ($l) => [$l->item => $disponibilidad->disponible($l->item)],
        );

        return [
            'totales' => $detalle['totales'],
            'lineas' => $paginador,
            'categorias' => $categorias,
            'puedeEditar' => auth()->user()->can(Permisos::SEGUIMIENTO_EDITAR),
            'estados' => EstadoSeguimiento::valores(),
            'estadosMateriales' => EstadoMateriales::valores(),
            'disponibles' => $disponibles,
        ];
    }
}; ?>

<x-ui.page :title="'Pedido ' . $pedido">
    <x-slot:actions>
        <a href="{{ route('pedidos.index') }}" wire:navigate class="btn-secondary">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" /></svg>
            Volver
        </a>
    </x-slot:actions>

    {{-- Cabecera del pedido --}}
    <x-ui.card class="p-5">
        <div class="grid grid-cols-2 gap-x-6 gap-y-4 sm:grid-cols-3 lg:grid-cols-4">
            <div>
                <div class="kv-label">Cliente</div>
                <div class="kv-value">{{ $totales->nombre_cliente }}</div>
                <div class="text-xs text-ink-400">{{ $totales->nit_cliente }}</div>
            </div>
            <div><div class="kv-label">Destino</div><div class="kv-value">{{ $totales->destino }}</div></div>
            <div><div class="kv-label">Estado ERP</div><div class="mt-0.5"><x-ui.badge color="blue">{{ $totales->estado }}</x-ui.badge></div></div>
            <div><div class="kv-label">Revisión</div><div class="mt-0.5"><x-ui.badge :color="$totales->estado_revision === 'REVISADO' ? 'green' : 'gray'">{{ $totales->estado_revision }}</x-ui.badge></div></div>
            <div><div class="kv-label">Fecha de solicitud</div><div class="kv-value">{{ $totales->fecha_solicitud ? \Illuminate\Support\Carbon::parse($totales->fecha_solicitud)->format('d/m/Y') : '—' }}</div></div>
            <div><div class="kv-label">Cantidad total</div><div class="kv-value tabular-nums">{{ number_format((float) $totales->cantidad_total, 0, ',', '.') }}</div></div>
            <div>
                <div class="kv-label">Toneladas totales</div>
                <div class="kv-value tabular-nums">
                    {{ number_format((float) $totales->toneladas_total, 1, ',', '.') }}
                    @if ($totales->toneladas_incompleto)<x-ui.badge color="amber">incompleto</x-ui.badge>@endif
                </div>
            </div>
            <div>
                <div class="kv-label">Cajas totales</div>
                <div class="kv-value tabular-nums">{{ number_format((float) $totales->cajas_total, 1, ',', '.') }}</div>
            </div>
            <div>
                <div class="kv-label">Volumen total (m³)</div>
                <div class="kv-value tabular-nums">
                    {{ number_format((float) $totales->volumen_total, 2, ',', '.') }}
                    @if ($totales->volumen_incompleto)<x-ui.badge color="amber">incompleto</x-ui.badge>@endif
                </div>
            </div>
            <div>
                <div class="kv-label">Contenedor sugerido</div>
                <div class="kv-value">
                    @if ($totales->contenedor_sugerido === 'Excede cubicaje')
                        <x-ui.badge color="red">{{ $totales->contenedor_sugerido }}</x-ui.badge>
                    @elseif ($totales->contenedor_sugerido)
                        {{ $totales->contenedor_sugerido }}
                    @else — @endif
                </div>
            </div>
            <div>
                <div class="kv-label">Inventario / % cumpl.</div>
                <div class="kv-value tabular-nums">
                    {{ $totales->inventario_total !== null ? number_format((float) $totales->inventario_total, 0, ',', '.') : '—' }}
                    @if ($totales->porcentaje_cumplimiento !== null)
                        <span class="font-normal text-ink-500">({{ number_format((float) $totales->porcentaje_cumplimiento, 1, ',', '.') }}%)</span>
                    @endif
                </div>
            </div>
        </div>
    </x-ui.card>

    {{-- Seguimiento a nivel de pedido --}}
    <x-ui.card title="Seguimiento del pedido">
        <div class="flex flex-wrap items-end gap-4">
            <div>
                <label class="lbl">Estado de seguimiento</label>
                <select wire:model="estadoSeguimiento" @disabled(! $puedeEditar) class="field-sm">
                    @foreach ($estados as $op) <option value="{{ $op }}">{{ $op }}</option> @endforeach
                </select>
            </div>
            <div>
                <label class="lbl">Fecha tentativa de cargue</label>
                <input type="date" wire:model="fechaTentativa" @disabled(! $puedeEditar) class="field-sm">
            </div>
            <div class="pb-1 text-2xl leading-none">
                @php $al = match ($estadoSeguimiento) { 'Pendiente' => '🔴', 'En validación' => '🟡', 'Listo para cargar' => '✅', default => '' }; @endphp
                {{ $al }}
            </div>
            @if ($puedeEditar)
                <button wire:click="guardarPedido" class="btn-primary">Guardar pedido</button>
            @endif
        </div>
        @error('estadoSeguimiento') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
        @error('fechaTentativa') <p class="mt-2 text-sm text-red-600">{{ $message }}</p> @enderror
    </x-ui.card>

    {{-- Seguimiento a nivel de línea --}}
    <x-ui.card title="Detalle de líneas" flush>
        <x-slot:header>
            <div class="flex items-center gap-3">
                <label class="lbl !mb-0">Parámetro</label>
                <select wire:model.live="categoria" class="field-sm">
                    <option value="">Todas las categorías</option>
                    @foreach ($categorias as $c) <option value="{{ $c }}">{{ $c }}</option> @endforeach
                </select>
                <span class="text-xs text-ink-400">{{ $lineas->total() }} ítem(s)</span>
            </div>
        </x-slot:header>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead>
                <tr>
                    <th>Ítem</th>
                    <th>Descripción</th>
                    <th class="!text-right">Cant. pedida</th>
                    <th class="!text-right">Toneladas</th>
                    <th class="!text-right">Cajas</th>
                    <th class="!text-right">Volumen m³</th>
                    <th>Estado</th>
                    <th>Observaciones</th>
                    <th>Fecha programada</th>
                    <th class="!text-right">Inventario</th>
                    <th class="!text-right">% Cumpl.</th>
                </tr>
                </thead>
                <tbody>
                @forelse ($lineas as $l)
                    @php
                        $inv = isset($lineasSeg[$l->item]['inventario']) && $lineasSeg[$l->item]['inventario'] !== null && $lineasSeg[$l->item]['inventario'] !== ''
                            ? (float) $lineasSeg[$l->item]['inventario'] : null;
                        $pct = $inv !== null && (float) $l->cantidad_pedida > 0 ? round($inv / (float) $l->cantidad_pedida * 100, 2) : null;
                        $dispItem = (float) ($disponibles[$l->item] ?? 0);
                        $dispInsuficiente = $dispItem < (float) $l->cantidad_pedida;
                    @endphp
                    <tr wire:key="lin-{{ $l->item }}">
                        <td class="font-semibold text-ink-900">{{ $l->item }}</td>
                        <td>
                            <div class="text-ink-900">{{ $l->descripcion }}</div>
                            <div class="text-xs text-ink-400">{{ $l->grupo_item }}</div>
                        </td>
                        <td class="num whitespace-nowrap">{{ number_format((float) $l->cantidad_pedida, 0, ',', '.') }} <span class="text-xs text-ink-400">{{ $l->unidad_medida }}</span></td>
                        <td class="num whitespace-nowrap">
                            @if ($l->sin_factor)<x-ui.badge color="amber">sin factor</x-ui.badge>
                            @else {{ number_format((float) $l->toneladas_linea, 1, ',', '.') }} @endif
                        </td>
                        <td class="num whitespace-nowrap">
                            {{ $l->cajas_linea !== null ? number_format((float) $l->cajas_linea, 1, ',', '.') : '—' }}
                        </td>
                        <td class="num whitespace-nowrap">
                            @if ($l->sin_volumen)<x-ui.badge color="amber">sin volumen</x-ui.badge>
                            @else {{ number_format((float) $l->volumen_linea, 2, ',', '.') }} @endif
                        </td>
                        <td>
                            <select wire:model="lineasSeg.{{ $l->item }}.estado_materiales" @disabled(! $puedeEditar)
                                    class="field-sm w-52">
                                <option value="">—</option>
                                @foreach ($estadosMateriales as $em)
                                    <option value="{{ $em }}">{{ $em }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td>
                            <input wire:model="lineasSeg.{{ $l->item }}.observaciones" @disabled(! $puedeEditar)
                                   class="field-sm w-48" placeholder="—">
                        </td>
                        <td>
                            <input type="date" wire:model="lineasSeg.{{ $l->item }}.fecha_programa" @disabled(! $puedeEditar)
                                   class="field-sm w-36">
                        </td>
                        <td class="num">
                            <input type="number" step="0.001" min="0" wire:model.live="lineasSeg.{{ $l->item }}.inventario" @disabled(! $puedeEditar)
                                   class="field-sm w-28 text-right" placeholder="—">
                            <div @class(['mt-0.5 text-xs', 'text-red-600 font-semibold' => $dispInsuficiente, 'text-ink-400' => ! $dispInsuficiente])>
                                Disp.: {{ \App\Support\Numero::decimal($dispItem, 3) }}
                            </div>
                            @error('lineasSeg.'.$l->item.'.inventario') <p class="mt-0.5 text-xs text-red-600">{{ $message }}</p> @enderror
                        </td>
                        <td class="num">
                            @if ($pct !== null)
                                <span @class(['font-medium', 'text-emerald-600' => $pct >= 100, 'text-amber-600' => $pct < 100])>{{ number_format($pct, 1, ',', '.') }}%</span>
                            @else — @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="11" class="!py-8 text-center text-ink-400">Sin líneas para esa categoría.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.card>

    <div class="flex items-center justify-between">
        <div>{{ $lineas->links() }}</div>
        @if ($puedeEditar)
            <button wire:click="guardarLineas" class="btn-primary">Guardar líneas</button>
        @endif
    </div>
</x-ui.page>
