import './bootstrap';
import Swal from 'sweetalert2';
import 'sweetalert2/dist/sweetalert2.min.css';

window.Swal = Swal;

window.Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3500,
    timerProgressBar: true,
    didOpen: (toast) => {
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    },
});

/**
 * Formatea un número mientras se escribe: punto de miles en la parte entera,
 * coma como separador decimal (una sola). Devuelve el texto a mostrar en el
 * input y el valor numérico "plano" (punto decimal) para enviar al backend.
 */
window.formatoMilesInput = function (valorEscrito) {
    const limpio = String(valorEscrito ?? '').replace(/[^\d,]/g, '');
    const idxComa = limpio.indexOf(',');
    let entero = idxComa === -1 ? limpio : limpio.slice(0, idxComa);
    const decimal = idxComa === -1 ? '' : limpio.slice(idxComa + 1).replace(/,/g, '');
    entero = entero.replace(/^0+(?=\d)/, '');
    const enteroFormateado = entero.replace(/\B(?=(\d{3})+(?!\d))/g, '.');

    return {
        mostrado: idxComa === -1 ? enteroFormateado : `${enteroFormateado},${decimal}`,
        numerico: entero === '' && decimal === '' ? '' : `${entero || '0'}${idxComa === -1 ? '' : '.' + decimal}`,
    };
};
