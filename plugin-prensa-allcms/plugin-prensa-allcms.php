<?php
/**
 * Plugin Name: Plugin Prensa AllCMS
 * Description: Plugin propio para gestionar noticias de prensa con logos principales, descripción y enlaces de noticias que muestran automáticamente el logo del medio usando Media Logos.
 * Version: 1.0.3
 * Author: Lucas Román y Víctor Nieves
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/* =========================================================
   1. CREAR TABLA AL ACTIVAR EL PLUGIN
========================================================= */

register_activation_hook( __FILE__, 'ppa_crear_tabla_prensa' );

function ppa_crear_tabla_prensa() {
    global $wpdb;

    $tabla   = $wpdb->prefix . 'prensa_allcms';
    $charset = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $tabla (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        fecha VARCHAR(100) NOT NULL,
        logo_principal_1 TEXT NULL,
        logo_principal_2 TEXT NULL,
        titulo TEXT NOT NULL,
        descripcion LONGTEXT NULL,
        texto_lectura VARCHAR(255) DEFAULT 'Lee la noticia en',
        logos_secundarios LONGTEXT NULL,
        creado DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset;";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( $sql );
}

/* =========================================================
   2. CARGAR CSS Y JS
========================================================= */

add_action( 'admin_enqueue_scripts', 'ppa_cargar_assets_admin' );

function ppa_cargar_assets_admin( $hook ) {
    if (
        $hook !== 'toplevel_page_prensa-allcms' &&
        $hook !== 'prensa_page_prensa-allcms-nueva'
    ) {
        return;
    }

    wp_enqueue_media();

    wp_enqueue_style(
        'ppa-admin-css',
        plugin_dir_url( __FILE__ ) . 'assets/css/admin.css',
        [],
        '1.0.3'
    );

    wp_enqueue_script(
        'ppa-admin-js',
        plugin_dir_url( __FILE__ ) . 'assets/js/admin.js',
        [ 'jquery' ],
        '1.0.3',
        true
    );
}

add_action( 'wp_enqueue_scripts', 'ppa_cargar_assets_frontend' );

function ppa_cargar_assets_frontend() {
    wp_enqueue_style(
        'ppa-frontend-css',
        plugin_dir_url( __FILE__ ) . 'assets/css/frontend.css',
        [],
        '1.0.3'
    );
}

/* =========================================================
   3. MENÚ DE ADMINISTRACIÓN
========================================================= */

add_action( 'admin_menu', 'ppa_agregar_menu' );

function ppa_agregar_menu() {
    add_menu_page(
        'Prensa AllCMS',
        'Prensa',
        'manage_options',
        'prensa-allcms',
        'ppa_pagina_listado',
        'dashicons-media-document',
        31
    );

    add_submenu_page(
        'prensa-allcms',
        'Añadir noticia',
        'Añadir noticia',
        'manage_options',
        'prensa-allcms-nueva',
        'ppa_pagina_formulario'
    );
}

/* =========================================================
   4. FUNCIONES AUXILIARES PARA MEDIA LOGOS
========================================================= */

function ppa_normalizar_dominio_desde_url( $url ) {
    $url = trim( $url );

    if ( empty( $url ) ) {
        return '';
    }

    if ( ! preg_match( '#^https?://#i', $url ) ) {
        $url = 'https://' . $url;
    }

    $partes = wp_parse_url( $url );

    if ( empty( $partes['host'] ) ) {
        return '';
    }

    $dominio = strtolower( $partes['host'] );
    $dominio = preg_replace( '/^www\./i', '', $dominio );

    return $dominio;
}

function ppa_obtener_medio_por_url_noticia( $url_noticia ) {
    global $wpdb;

    $url_noticia = trim( $url_noticia );

    if ( empty( $url_noticia ) ) {
        return [
            'nombre' => '',
            'logo'   => '',
            'url'    => '',
        ];
    }

    if ( ! preg_match( '#^https?://#i', $url_noticia ) ) {
        $url_noticia = 'https://' . $url_noticia;
    }

    $dominio_noticia = ppa_normalizar_dominio_desde_url( $url_noticia );

    if ( empty( $dominio_noticia ) ) {
        return [
            'nombre' => '',
            'logo'   => '',
            'url'    => esc_url_raw( $url_noticia ),
        ];
    }

    $tabla_media_logos = $wpdb->prefix . 'media_logos';

    $tabla_existe = $wpdb->get_var(
        $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $tabla_media_logos
        )
    );

    if ( $tabla_existe !== $tabla_media_logos ) {
        return [
            'nombre' => $dominio_noticia,
            'logo'   => '',
            'url'    => esc_url_raw( $url_noticia ),
        ];
    }

    $medios = $wpdb->get_results(
        "SELECT nombre, dominio, logo_url FROM $tabla_media_logos ORDER BY nombre ASC"
    );

    if ( empty( $medios ) ) {
        return [
            'nombre' => $dominio_noticia,
            'logo'   => '',
            'url'    => esc_url_raw( $url_noticia ),
        ];
    }

    foreach ( $medios as $medio ) {
        $dominio_guardado = strtolower( trim( $medio->dominio ) );
        $dominio_guardado = preg_replace( '#^https?://#i', '', $dominio_guardado );
        $dominio_guardado = preg_replace( '/^www\./i', '', $dominio_guardado );
        $dominio_guardado = rtrim( $dominio_guardado, '/' );

        if (
            $dominio_noticia === $dominio_guardado ||
            str_ends_with( $dominio_noticia, '.' . $dominio_guardado )
        ) {
            return [
                'nombre' => sanitize_text_field( $medio->nombre ),
                'logo'   => esc_url_raw( $medio->logo_url ),
                'url'    => esc_url_raw( $url_noticia ),
            ];
        }
    }

    return [
        'nombre' => $dominio_noticia,
        'logo'   => '',
        'url'    => esc_url_raw( $url_noticia ),
    ];
}

/* =========================================================
   5. LISTADO DE NOTICIAS
========================================================= */

function ppa_pagina_listado() {
    global $wpdb;

    $tabla = $wpdb->prefix . 'prensa_allcms';
    ppa_crear_tabla_prensa();

    $noticias = $wpdb->get_results( "SELECT * FROM $tabla ORDER BY creado DESC" );
    ?>

    <div class="wrap">
        <h1 class="wp-heading-inline">Noticias de prensa</h1>

        <a href="<?php echo esc_url( admin_url( 'admin.php?page=prensa-allcms-nueva' ) ); ?>" class="page-title-action">
            Añadir noticia
        </a>

        <hr class="wp-header-end">

        <?php if ( isset( $_GET['guardado'] ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p>Noticia guardada correctamente.</p>
            </div>
        <?php endif; ?>

        <?php if ( isset( $_GET['eliminado'] ) ) : ?>
            <div class="notice notice-success is-dismissible">
                <p>Noticia eliminada correctamente.</p>
            </div>
        <?php endif; ?>

        <?php if ( isset( $_GET['error'] ) ) : ?>
            <div class="notice notice-error is-dismissible">
                <p>Error: <?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p>
            </div>
        <?php endif; ?>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width:120px;">Fecha</th>
                    <th>Título</th>
                    <th style="width:180px;">Logos principales</th>
                    <th style="width:170px;">Acciones</th>
                </tr>
            </thead>

            <tbody>
                <?php if ( $noticias ) : ?>
                    <?php foreach ( $noticias as $noticia ) : ?>
                        <tr>
                            <td><?php echo esc_html( $noticia->fecha ); ?></td>

                            <td>
                                <strong><?php echo esc_html( $noticia->titulo ); ?></strong>
                            </td>

                            <td>
                                <?php if ( $noticia->logo_principal_1 ) : ?>
                                    <img src="<?php echo esc_url( $noticia->logo_principal_1 ); ?>" class="ppa-admin-table-logo">
                                <?php endif; ?>

                                <?php if ( $noticia->logo_principal_2 ) : ?>
                                    <img src="<?php echo esc_url( $noticia->logo_principal_2 ); ?>" class="ppa-admin-table-logo">
                                <?php endif; ?>
                            </td>

                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=prensa-allcms-nueva&id=' . $noticia->id ) ); ?>">
                                    Editar
                                </a>

                                &nbsp;|&nbsp;

                                <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ppa_eliminar_noticia&id=' . $noticia->id ), 'ppa_eliminar_noticia_nonce' ) ); ?>"
                                   onclick="return confirm('¿Eliminar esta noticia?');"
                                   style="color:#b32d2e;">
                                    Eliminar
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else : ?>
                    <tr>
                        <td colspan="4">Todavía no hay noticias de prensa creadas.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="margin-top:20px;">
            Shortcode para mostrar las noticias:
            <code>[prensa_grid]</code>
        </p>
    </div>

    <?php
}

/* =========================================================
   6. FORMULARIO AÑADIR / EDITAR
========================================================= */

function ppa_pagina_formulario() {
    global $wpdb;

    $tabla = $wpdb->prefix . 'prensa_allcms';
    ppa_crear_tabla_prensa();

    $id      = intval( $_GET['id'] ?? 0 );
    $noticia = null;

    if ( $id > 0 ) {
        $noticia = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM $tabla WHERE id = %d", $id )
        );
    }

    $enlaces_noticias = [];

    if ( $noticia && ! empty( $noticia->logos_secundarios ) ) {
        $enlaces_noticias = json_decode( $noticia->logos_secundarios, true );

        if ( ! is_array( $enlaces_noticias ) ) {
            $enlaces_noticias = [];
        }
    }
    ?>

    <div class="wrap">
        <h1><?php echo $noticia ? 'Editar noticia de prensa' : 'Añadir noticia de prensa'; ?></h1>

        <?php if ( isset( $_GET['error'] ) ) : ?>
            <div class="notice notice-error">
                <p>La fecha y el título son obligatorios.</p>
            </div>
        <?php endif; ?>

        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'ppa_guardar_noticia_nonce' ); ?>

            <input type="hidden" name="action" value="ppa_guardar_noticia">
            <input type="hidden" name="ppa_id" value="<?php echo esc_attr( $noticia ? $noticia->id : 0 ); ?>">

            <table class="form-table">
                <tr>
                    <th><label for="ppa_fecha">Fecha</label></th>
                    <td>
                        <input type="text"
                               id="ppa_fecha"
                               name="ppa_fecha"
                               class="regular-text"
                               placeholder="Ej: 04/2026"
                               value="<?php echo esc_attr( $noticia->fecha ?? '' ); ?>"
                               required>
                    </td>
                </tr>

                <tr>
                    <th><label>Logos principales</label></th>
                    <td>
                        <?php
                        ppa_campo_imagen(
                            'ppa_logo_principal_1',
                            $noticia->logo_principal_1 ?? '',
                            'Logo principal 1'
                        );

                        echo '<br><br>';

                        ppa_campo_imagen(
                            'ppa_logo_principal_2',
                            $noticia->logo_principal_2 ?? '',
                            'Logo principal 2'
                        );
                        ?>
                    </td>
                </tr>

                <tr>
                    <th><label for="ppa_titulo">Título</label></th>
                    <td>
                        <input type="text"
                               id="ppa_titulo"
                               name="ppa_titulo"
                               class="large-text"
                               value="<?php echo esc_attr( $noticia->titulo ?? '' ); ?>"
                               required>
                    </td>
                </tr>

                <tr>
                    <th><label for="ppa_descripcion">Descripción</label></th>
                    <td>
                        <textarea id="ppa_descripcion"
                                  name="ppa_descripcion"
                                  class="large-text"
                                  rows="5"><?php echo esc_textarea( $noticia->descripcion ?? '' ); ?></textarea>
                    </td>
                </tr>

                <tr>
                    <th><label for="ppa_texto_lectura">Texto antes de los logos de medios</label></th>
                    <td>
                        <input type="text"
                               id="ppa_texto_lectura"
                               name="ppa_texto_lectura"
                               class="regular-text"
                               value="<?php echo esc_attr( $noticia->texto_lectura ?? 'Lee la noticia en' ); ?>">
                        <p class="description">Ejemplo: Lee la noticia en</p>
                    </td>
                </tr>

                <tr>
                    <th><label>Enlaces de noticias</label></th>
                    <td>
                        <p>
                            <label><strong>Pegar varios enlaces de golpe</strong></label><br>
                            <textarea name="ppa_urls_masivas"
                                      class="large-text"
                                      rows="10"
                                      placeholder="Pega aquí todos los enlaces, uno por línea"></textarea>
                        </p>

                        <p class="description">
                            Puedes pegar muchos enlaces a la vez, uno por línea. Al guardar, el plugin buscará automáticamente el logo de cada medio usando Media Logos.
                        </p>

                        <hr>

                        <p>
                            <strong>Enlaces guardados actualmente</strong>
                        </p>

                        <div id="ppa_enlaces_noticias">
                            <?php if ( ! empty( $enlaces_noticias ) ) : ?>
                                <?php foreach ( $enlaces_noticias as $enlace ) : ?>
                                    <?php ppa_bloque_enlace_noticia( $enlace ); ?>
                                <?php endforeach; ?>
                            <?php else : ?>
                                <?php ppa_bloque_enlace_noticia(); ?>
                            <?php endif; ?>
                        </div>

                        <button type="button" class="button" id="ppa_anadir_enlace_noticia">
                            Añadir enlace manualmente
                        </button>

                        <p class="description">
                            Si quieres añadir solo uno, usa el botón manual. Si quieres añadir muchos, usa el cuadro grande de arriba.
                        </p>
                    </td>
                </tr>
            </table>

            <?php submit_button( $noticia ? 'Actualizar noticia' : 'Guardar noticia' ); ?>
        </form>
    </div>

    <?php
}

/* =========================================================
   7. CAMPOS REUTILIZABLES
========================================================= */

function ppa_campo_imagen( $name, $value = '', $label = 'Imagen' ) {
    $input_id   = $name;
    $preview_id = $name . '_preview';
    ?>

    <div class="ppa-image-field">
        <label><strong><?php echo esc_html( $label ); ?></strong></label><br>

        <img id="<?php echo esc_attr( $preview_id ); ?>"
             src="<?php echo esc_url( $value ); ?>"
             class="ppa-preview-img"
             style="<?php echo $value ? '' : 'display:none;'; ?>">

        <input type="hidden"
               id="<?php echo esc_attr( $input_id ); ?>"
               name="<?php echo esc_attr( $name ); ?>"
               value="<?php echo esc_url( $value ); ?>">

        <button type="button"
                class="button ppa-subir-imagen"
                data-input="<?php echo esc_attr( $input_id ); ?>"
                data-preview="<?php echo esc_attr( $preview_id ); ?>">
            Subir logo
        </button>
    </div>

    <?php
}

function ppa_bloque_enlace_noticia( $enlace = [] ) {
    $url    = $enlace['url'] ?? '';
    $logo   = $enlace['logo'] ?? '';
    $nombre = $enlace['nombre'] ?? '';
    ?>

    <div class="ppa-enlace-noticia">
        <p>
            <label><strong>Enlace de la noticia</strong></label><br>
            <input type="url"
                   name="ppa_url_noticia[]"
                   class="large-text"
                   placeholder="Ej: https://www.eleconomista.es/..."
                   value="<?php echo esc_url( $url ); ?>">
        </p>

        <p class="description">
            El plugin buscará automáticamente el logo del medio usando Media Logos.
        </p>

        <?php if ( ! empty( $logo ) ) : ?>
            <p>
                <strong>Logo detectado:</strong><br>
                <img src="<?php echo esc_url( $logo ); ?>" class="ppa-preview-img">
            </p>
        <?php endif; ?>

        <?php if ( ! empty( $nombre ) ) : ?>
            <p class="description">
                Medio detectado: <strong><?php echo esc_html( $nombre ); ?></strong>
            </p>
        <?php endif; ?>

        <button type="button" class="button button-link-delete ppa-eliminar-enlace-noticia">
            Eliminar enlace
        </button>
    </div>

    <?php
}

/* =========================================================
   8. GUARDAR NOTICIA
========================================================= */

add_action( 'admin_post_ppa_guardar_noticia', 'ppa_guardar_noticia' );

function ppa_guardar_noticia() {
    check_admin_referer( 'ppa_guardar_noticia_nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sin permisos.' );
    }

    global $wpdb;

    $tabla = $wpdb->prefix . 'prensa_allcms';
    ppa_crear_tabla_prensa();

    $id = intval( $_POST['ppa_id'] ?? 0 );

    $fecha             = sanitize_text_field( $_POST['ppa_fecha'] ?? '' );
    $logo_principal_1  = esc_url_raw( $_POST['ppa_logo_principal_1'] ?? '' );
    $logo_principal_2  = esc_url_raw( $_POST['ppa_logo_principal_2'] ?? '' );
    $titulo            = sanitize_text_field( $_POST['ppa_titulo'] ?? '' );
    $descripcion       = sanitize_textarea_field( $_POST['ppa_descripcion'] ?? '' );
    $texto_lectura     = sanitize_text_field( $_POST['ppa_texto_lectura'] ?? 'Lee la noticia en' );

    if ( ! $fecha || ! $titulo ) {
        wp_redirect( add_query_arg( [
            'page'  => 'prensa-allcms-nueva',
            'error' => 'campos'
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    $enlaces_noticias = [];
    $urls_procesadas  = [];

    /*
     * 1. Enlaces individuales ya existentes o añadidos manualmente.
     */
    if ( isset( $_POST['ppa_url_noticia'] ) && is_array( $_POST['ppa_url_noticia'] ) ) {
        foreach ( $_POST['ppa_url_noticia'] as $url_noticia ) {
            $url_noticia = trim( $url_noticia );

            if ( empty( $url_noticia ) ) {
                continue;
            }

            if ( ! preg_match( '#^https?://#i', $url_noticia ) ) {
                $url_noticia = 'https://' . $url_noticia;
            }

            $url_clave = strtolower( rtrim( $url_noticia, '/' ) );

            if ( in_array( $url_clave, $urls_procesadas, true ) ) {
                continue;
            }

            $urls_procesadas[] = $url_clave;

            $medio = ppa_obtener_medio_por_url_noticia( $url_noticia );

            if ( ! empty( $medio['url'] ) ) {
                $enlaces_noticias[] = [
                    'nombre' => sanitize_text_field( $medio['nombre'] ),
                    'logo'   => esc_url_raw( $medio['logo'] ),
                    'url'    => esc_url_raw( $medio['url'] ),
                ];
            }
        }
    }

    /*
     * 2. Enlaces pegados en bloque desde el textarea grande.
     */
    $urls_masivas = sanitize_textarea_field( $_POST['ppa_urls_masivas'] ?? '' );

    if ( ! empty( $urls_masivas ) ) {
        $lineas = preg_split( '/[\r\n]+/', $urls_masivas );

        foreach ( $lineas as $url_noticia ) {
            $url_noticia = trim( $url_noticia );

            if ( empty( $url_noticia ) ) {
                continue;
            }

            if ( ! preg_match( '#^https?://#i', $url_noticia ) ) {
                $url_noticia = 'https://' . $url_noticia;
            }

            $url_clave = strtolower( rtrim( $url_noticia, '/' ) );

            if ( in_array( $url_clave, $urls_procesadas, true ) ) {
                continue;
            }

            $urls_procesadas[] = $url_clave;

            $medio = ppa_obtener_medio_por_url_noticia( $url_noticia );

            if ( ! empty( $medio['url'] ) ) {
                $enlaces_noticias[] = [
                    'nombre' => sanitize_text_field( $medio['nombre'] ),
                    'logo'   => esc_url_raw( $medio['logo'] ),
                    'url'    => esc_url_raw( $medio['url'] ),
                ];
            }
        }
    }

    $datos = [
        'fecha'             => $fecha,
        'logo_principal_1'  => $logo_principal_1,
        'logo_principal_2'  => $logo_principal_2,
        'titulo'            => $titulo,
        'descripcion'       => $descripcion,
        'texto_lectura'     => $texto_lectura,
        'logos_secundarios' => wp_json_encode( $enlaces_noticias ),
    ];

    if ( $id > 0 ) {
        $resultado = $wpdb->update(
            $tabla,
            $datos,
            [ 'id' => $id ]
        );
    } else {
        $resultado = $wpdb->insert(
            $tabla,
            $datos
        );
    }

    if ( $resultado === false ) {
        wp_redirect( add_query_arg( [
            'page'  => 'prensa-allcms',
            'error' => urlencode( $wpdb->last_error )
        ], admin_url( 'admin.php' ) ) );
        exit;
    }

    wp_redirect( add_query_arg( [
        'page'     => 'prensa-allcms',
        'guardado' => '1'
    ], admin_url( 'admin.php' ) ) );
    exit;
}

/* =========================================================
   9. ELIMINAR NOTICIA
========================================================= */

add_action( 'admin_post_ppa_eliminar_noticia', 'ppa_eliminar_noticia' );

function ppa_eliminar_noticia() {
    check_admin_referer( 'ppa_eliminar_noticia_nonce' );

    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sin permisos.' );
    }

    $id = intval( $_GET['id'] ?? 0 );

    if ( $id > 0 ) {
        global $wpdb;
        $tabla = $wpdb->prefix . 'prensa_allcms';

        $wpdb->delete( $tabla, [ 'id' => $id ] );
    }

    wp_redirect( add_query_arg( [
        'page'      => 'prensa-allcms',
        'eliminado' => '1'
    ], admin_url( 'admin.php' ) ) );
    exit;
}

/* =========================================================
   10. SHORTCODE FRONTEND
   Uso: [prensa_grid]
========================================================= */

add_shortcode( 'prensa_grid', 'ppa_shortcode_prensa_grid' );

function ppa_shortcode_prensa_grid() {
    global $wpdb;

    $tabla = $wpdb->prefix . 'prensa_allcms';
    ppa_crear_tabla_prensa();

    $noticias = $wpdb->get_results( "SELECT * FROM $tabla ORDER BY creado DESC" );

    if ( ! $noticias ) {
        return '<p>No hay noticias de prensa disponibles.</p>';
    }

    ob_start();
    ?>

    <div class="ppa-prensa-grid">
        <?php foreach ( $noticias as $noticia ) : ?>
            <?php
            $enlaces_noticias = [];

            if ( ! empty( $noticia->logos_secundarios ) ) {
                $enlaces_noticias = json_decode( $noticia->logos_secundarios, true );

                if ( ! is_array( $enlaces_noticias ) ) {
                    $enlaces_noticias = [];
                }
            }
            ?>

            <article class="ppa-prensa-card">
                <div class="ppa-prensa-left">
                    <?php if ( $noticia->fecha ) : ?>
                        <div class="ppa-prensa-fecha">
                            <?php echo esc_html( $noticia->fecha ); ?>
                        </div>
                    <?php endif; ?>

                    <div class="ppa-prensa-logos-principales">
                        <?php if ( $noticia->logo_principal_1 ) : ?>
                            <img class="ppa-prensa-logo-principal"
                                 src="<?php echo esc_url( $noticia->logo_principal_1 ); ?>"
                                 alt="">
                        <?php endif; ?>

                        <?php if ( $noticia->logo_principal_1 && $noticia->logo_principal_2 ) : ?>
                            <div class="ppa-prensa-separador"></div>
                        <?php endif; ?>

                        <?php if ( $noticia->logo_principal_2 ) : ?>
                            <img class="ppa-prensa-logo-principal"
                                 src="<?php echo esc_url( $noticia->logo_principal_2 ); ?>"
                                 alt="">
                        <?php endif; ?>
                    </div>

                    <h2 class="ppa-prensa-titulo">
                        <?php echo esc_html( $noticia->titulo ); ?>
                    </h2>
                </div>

                <div class="ppa-prensa-right">
                    <?php if ( $noticia->descripcion ) : ?>
                        <p class="ppa-prensa-descripcion">
                            <?php echo esc_html( $noticia->descripcion ); ?>
                        </p>
                    <?php endif; ?>

                    <?php if ( $noticia->texto_lectura ) : ?>
                        <div class="ppa-prensa-texto-lectura">
                            <?php echo esc_html( $noticia->texto_lectura ); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ( ! empty( $enlaces_noticias ) ) : ?>
                        <div class="ppa-prensa-logos-secundarios">
                            <?php foreach ( $enlaces_noticias as $enlace ) : ?>
                                <?php
                                $url    = $enlace['url'] ?? '';
                                $logo   = $enlace['logo'] ?? '';
                                $nombre = $enlace['nombre'] ?? '';
                                ?>

                                <?php if ( ! empty( $url ) ) : ?>
                                    <a href="<?php echo esc_url( $url ); ?>"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       title="<?php echo esc_attr( $nombre ); ?>">

                                        <?php if ( ! empty( $logo ) ) : ?>
                                            <img class="ppa-prensa-logo-secundario"
                                                 src="<?php echo esc_url( $logo ); ?>"
                                                 alt="<?php echo esc_attr( $nombre ); ?>">
                                        <?php else : ?>
                                            <span class="ppa-prensa-logo-fallback">
                                                <?php echo esc_html( $nombre ?: 'Ver noticia' ); ?>
                                            </span>
                                        <?php endif; ?>

                                    </a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>

        <?php endforeach; ?>
    </div>

    <?php
    return ob_get_clean();
}