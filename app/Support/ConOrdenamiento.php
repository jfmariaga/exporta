<?php

namespace App\Support;

use Livewire\Attributes\Url;

/**
 * Estado + toggle de "ordenar por columna" para tablas Livewire/Volt.
 * `ordenCampo` vacío significa "sin ordenar explícitamente": cada pantalla
 * conserva su orden por defecto hasta que el usuario hace clic en un
 * encabezado. `campoOrdenValido()` es una lista blanca obligatoria: nunca se
 * debe interpolar `$ordenCampo` crudo en una consulta.
 */
trait ConOrdenamiento
{
    #[Url(as: 'orden')]
    public string $ordenCampo = '';

    #[Url(as: 'dir')]
    public string $ordenDir = 'asc';

    public function ordenarPor(string $campo): void
    {
        if ($this->ordenCampo === $campo) {
            $this->ordenDir = $this->ordenDir === 'asc' ? 'desc' : 'asc';
        } else {
            $this->ordenCampo = $campo;
            $this->ordenDir = 'asc';
        }

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /**
     * @param  list<string>  $permitidos
     */
    protected function campoOrdenValido(array $permitidos): ?string
    {
        return in_array($this->ordenCampo, $permitidos, true) ? $this->ordenCampo : null;
    }
}
