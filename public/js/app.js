/*
 * Pide confirmación antes de enviar formularios o seguir enlaces
 * marcados con data-confirmar="Mensaje".
 */
document.addEventListener("submit", function (evento) {
    const mensaje = evento.target.dataset.confirmar;

    if (mensaje && !confirm(mensaje)) {
        evento.preventDefault();
    }
});
