jQuery(function ($) {
    function abrirMediaUploader(input, preview) {
        var frame = wp.media({
            title: "Seleccionar logo",
            button: {
                text: "Usar esta imagen"
            },
            multiple: false,
            library: {
                type: "image"
            }
        });

        frame.on("select", function () {
            var attachment = frame.state().get("selection").first().toJSON();

            input.val(attachment.url);
            preview.attr("src", attachment.url).show();
        });

        frame.open();
    }

    $(document).on("click", ".ppa-subir-imagen", function (e) {
        e.preventDefault();

        var inputId = $(this).data("input");
        var previewId = $(this).data("preview");

        abrirMediaUploader($("#" + inputId), $("#" + previewId));
    });

    $("#ppa_anadir_enlace_noticia").on("click", function () {
        var bloque = `
            <div class="ppa-enlace-noticia">
                <p>
                    <label><strong>Enlace de la noticia</strong></label><br>
                    <input type="url" name="ppa_url_noticia[]" class="large-text" placeholder="Ej: https://www.eleconomista.es/...">
                </p>

                <p class="description">
                    El plugin buscará automáticamente el logo del medio usando el dominio registrado en Media Logos.
                </p>

                <button type="button" class="button button-link-delete ppa-eliminar-enlace-noticia">
                    Eliminar enlace
                </button>
            </div>
        `;

        $("#ppa_enlaces_noticias").append(bloque);
    });

    $(document).on("click", ".ppa-eliminar-enlace-noticia", function () {
        $(this).closest(".ppa-enlace-noticia").remove();
    });
});