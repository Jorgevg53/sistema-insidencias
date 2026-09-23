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

/*
 * Actualiza cada minuto el contador de notificaciones de la campana.
 */
(function () {
    const contador = document.querySelector("[data-contador-notificaciones]");

    if (!contador) {
        return;
    }

    function actualizar() {
        fetch("notificaciones_contador.php", { credentials: "same-origin" })
            .then(function (respuesta) {
                return respuesta.ok ? respuesta.json() : null;
            })
            .then(function (datos) {
                if (!datos || typeof datos.no_leidas !== "number") {
                    return;
                }

                contador.textContent = datos.no_leidas > 99 ? "99+" : datos.no_leidas;
                contador.hidden = datos.no_leidas === 0;
            })
            .catch(function () {
                // Sin conexión: se intenta de nuevo en el siguiente ciclo.
            });
    }

    setInterval(actualizar, 60000);
})();

/*
 * Botones "Imprimir / Guardar PDF" (data-imprimir).
 */
document.addEventListener("click", function (evento) {
    if (evento.target.closest("[data-imprimir]")) {
        window.print();
    }
});

/*
 * Revisa cantidad y tamaño de las evidencias antes de enviarlas
 * (el servidor vuelve a validar todo).
 */
document.addEventListener("change", function (evento) {
    const campo = evento.target;

    if (!campo.matches('input[type="file"][data-max-archivos]')) {
        return;
    }

    const maxArchivos = Number(campo.dataset.maxArchivos);
    const maxBytes = Number(campo.dataset.maxBytes);
    let mensaje = "";

    if (campo.files.length > maxArchivos) {
        mensaje = "Puedes adjuntar máximo " + maxArchivos + " archivos.";
    } else {
        for (const archivo of campo.files) {
            if (archivo.size > maxBytes) {
                mensaje = "«" + archivo.name + "» pesa más de " + Math.round(maxBytes / 1048576) + " MB.";
                break;
            }
        }
    }

    campo.setCustomValidity(mensaje);
    campo.reportValidity();
});
