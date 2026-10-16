<?php

namespace Tests\Feature;

use App\Support\Permisos;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\CreaUsuarios;
use Tests\TestCase;

class AutorizacionPedidosTest extends TestCase
{
    use CreaUsuarios, RefreshDatabase;

    public function test_rutas_exigen_permiso(): void
    {
        $sin = $this->usuarioCon([]);
        $this->actingAs($sin)->get('/pedidos-liberar')->assertForbidden();
        $this->actingAs($sin)->get('/cargues/correo')->assertForbidden();
        $this->actingAs($sin)->get('/admin/usuarios')->assertForbidden();
        $this->actingAs($sin)->get('/admin/roles')->assertForbidden();
        $this->actingAs($sin)->get('/admin/permisos')->assertForbidden();
    }

    public function test_con_permiso_pedidos_ver_accede_a_la_lista(): void
    {
        $this->actingAs($this->usuarioCon([Permisos::PEDIDOS_VER]))
            ->get('/pedidos-liberar')->assertOk();
    }

    public function test_invitado_es_redirigido_al_login(): void
    {
        $this->get('/pedidos-liberar')->assertRedirect('/login');
    }
}
