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

/*
 * Botones "Copiar" (data-copiar="#id-del-campo").
 */
document.addEventListener("click", function (evento) {
    const boton = evento.target.closest("[data-copiar]");

    if (!boton) {
        return;
    }

    const campo = document.querySelector(boton.dataset.copiar);

    campo.select();

    const listo = function () {
        boton.textContent = "¡Copiado!";
    };

    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(campo.value).then(listo);
    } else {
        document.execCommand("copy");
        listo();
    }
});

/*
 * Menú lateral en pantallas pequeñas (data-abrir-menu / data-cerrar-menu).
 */
(function () {
    const boton = document.querySelector("[data-abrir-menu]");
    const fondo = document.querySelector(".sidebar-fondo");

    if (!boton) {
        return;
    }

    function cambiar(abierto) {
        document.body.classList.toggle("menu-abierto", abierto);
        boton.setAttribute("aria-expanded", abierto ? "true" : "false");

        if (fondo) {
            fondo.hidden = !abierto;
        }
    }

    boton.addEventListener("click", function () {
        cambiar(true);
    });

    document.addEventListener("click", function (evento) {
        if (evento.target.closest("[data-cerrar-menu]")) {
            cambiar(false);
        }
    });

    document.addEventListener("keydown", function (evento) {
        if (evento.key === "Escape" && document.body.classList.contains("menu-abierto")) {
            cambiar(false);
            boton.focus();
        }
    });
})();

/*
 * Botón para mostrar u ocultar una contraseña (data-ver-password="#id").
 */
document.addEventListener("click", function (evento) {
    const boton = evento.target.closest("[data-ver-password]");

    if (!boton) {
        return;
    }

    const campo = document.querySelector(boton.dataset.verPassword);
    const mostrar = campo.type === "password";

    campo.type = mostrar ? "text" : "password";
    boton.classList.toggle("visible", mostrar);
    boton.setAttribute("aria-label", mostrar ? "Ocultar contraseña" : "Mostrar contraseña");
    boton.title = boton.getAttribute("aria-label");
});

/*
 * Muestra los nombres de los archivos elegidos en los campos de evidencias.
 */
document.addEventListener("change", function (evento) {
    const campo = evento.target;

    if (!campo.matches('input[type="file"]')) {
        return;
    }

    const zona = campo.closest(".zona-archivos");
    const lista = zona && zona.querySelector("[data-archivos-elegidos]");

    if (!lista) {
        return;
    }

    lista.textContent = campo.files.length
        ? Array.from(campo.files, function (archivo) { return archivo.name; }).join(" · ")
        : "";
    zona.classList.toggle("con-archivos", campo.files.length > 0);
});

/*
 * Listas desplegables que envían su formulario al cambiar
 * (p. ej. "Por página" de la paginación).
 */
document.addEventListener("change", function (evento) {
    if (evento.target.matches("select[data-autoenviar]")) {
        evento.target.form.submit();
    }
});
