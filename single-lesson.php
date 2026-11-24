<?php
/**
 * Custom Single Lesson Template - Modo Simulado x Modo Aula
 *
 * Controle central: basta escrever [simulado] na DESCRICAO do topico.
 *  - Topico COM [simulado]  -> header completo (grade de questoes + cronometro)
 *                              e agrupamento por area no menu lateral.
 *  - Topico SEM [simulado]  -> header enxuto (menu, concluir, navegacao)
 *                              e topico listado normalmente com suas licoes.
 *  - Topico com [orientacao] -> header enxuto (layout de aula), mas entra no
 *                              menu agrupado por area, como os simulados.
 *                              Use no titulo o mesmo padrao "Area - Nome".
 *
 * @package Tutor\Templates
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $post;
$content_id   = tutor_utils()->get_post_id();
$course_id    = tutor_utils()->get_course_id_by_subcontent( $content_id );
$user_id      = get_current_user_id();
$current_user = wp_get_current_user();

// ==========================================================================
// 1. FILTRO DE CURSO ESPECIFICO
// ==========================================================================
$custom_course_ids = array( 127272,68267 ); // <-- ALTERE AQUI PARA O ID DO SEU CURSO

if ( ! in_array( $course_id, $custom_course_ids ) ) {
    include tutor()->path . 'templates/single-lesson.php';
    return;
}

// ==========================================================================
// 1.1 GUIA DE INTEGRACAO (ONBOARDING TOUR)
// ==========================================================================
// IDs das aulas onde o guia abre SOZINHO (com o modal "Ver tutorial / Pular").
// Ex.: array( 128001, 128050 )
$tour_auto_lesson_ids = array( 127471,131835,131944,132162,132279 ); // <-- COLOQUE AQUI O(S) ID(S)

// IDs das aulas onde o botao "Guia" do header NAO deve aparecer.
// Ex.: array( 128100, 128101 )
$tour_button_exclude_ids = array( 77136,72713,72708,72709,72710,72711,72712,105774 ); // <-- COLOQUE AQUI O(S) ID(S)

// true  = o modal automatico aparece so na 1a visita do aluno a cada aula
//         configurada (fica salvo no navegador apos "Pular" ou concluir)
// false = o modal aparece em toda visita
$tour_auto_show_once = true;

$tour_is_auto    = in_array( (int) $content_id, array_map( 'intval', $tour_auto_lesson_ids ), true );
$tour_has_button = ! in_array( (int) $content_id, array_map( 'intval', $tour_button_exclude_ids ), true );
$tour_enabled    = $tour_is_auto || $tour_has_button;

// ==========================================================================
// MARCADOR DE MODO SIMULADO (LIDO NA DESCRICAO DO TOPICO)
// ==========================================================================
if ( ! defined( 'TUTOR_CUSTOM_EXAM_FLAG' ) ) {
    define( 'TUTOR_CUSTOM_EXAM_FLAG', 'simulado' );
}

/**
 * Verifica se a descricao do topico contem o marcador [simulado].
 * Aceita variacoes de caixa, acento ausente e espacos internos.
 */
if ( ! function_exists( 'tutor_custom_topic_is_exam' ) ) {
    function tutor_custom_topic_is_exam( $topic_id ) {
        static $cache = array();

        $topic_id = (int) $topic_id;
        if ( ! $topic_id ) {
            return false;
        }
        if ( isset( $cache[ $topic_id ] ) ) {
            return $cache[ $topic_id ];
        }

        $summary = tutor_custom_topic_summary( $topic_id );

        // [ espacos? simulado espacos? ] -> case insensitive
        $pattern = '/\[\s*' . preg_quote( TUTOR_CUSTOM_EXAM_FLAG, '/' ) . '\s*\]/iu';

        $cache[ $topic_id ] = (bool) preg_match( $pattern, $summary );

        return $cache[ $topic_id ];
    }
}

/**
 * Descricao do topico em texto puro (sem HTML nem entidades).
 */
if ( ! function_exists( 'tutor_custom_topic_summary' ) ) {
    function tutor_custom_topic_summary( $topic_id ) {
        $summary = get_post_field( 'post_content', (int) $topic_id );
        $summary = html_entity_decode( (string) $summary, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        return wp_strip_all_tags( $summary );
    }
}

/**
 * Verifica se a descricao do topico contem o marcador [orientacao]:
 * o topico entra no menu agrupado por area, mas com layout de aula.
 * Aceita [orientacao], [orientação], [orientacoes], [orientações]...
 */
if ( ! function_exists( 'tutor_custom_topic_is_grouped' ) ) {
    function tutor_custom_topic_is_grouped( $topic_id ) {
        static $cache = array();

        $topic_id = (int) $topic_id;
        if ( ! $topic_id ) {
            return false;
        }
        if ( isset( $cache[ $topic_id ] ) ) {
            return $cache[ $topic_id ];
        }

        $pattern = '/\[\s*orienta[cç](?:[aã]o|[oõ]es)\s*\]/iu';

        $cache[ $topic_id ] = (bool) preg_match( $pattern, tutor_custom_topic_summary( $topic_id ) );

        return $cache[ $topic_id ];
    }
}

/**
 * Remove o marcador do texto exibido ao aluno.
 */
if ( ! function_exists( 'tutor_custom_strip_exam_flag' ) ) {
    function tutor_custom_strip_exam_flag( $text ) {
        $pattern = '/\[\s*' . preg_quote( TUTOR_CUSTOM_EXAM_FLAG, '/' ) . '\s*\]/iu';
        return trim( preg_replace( $pattern, '', (string) $text ) );
    }
}

// ==========================================================================
// FUNCAO AUXILIAR DE DIVISAO DE TITULOS
// ==========================================================================
if ( ! function_exists( 'tutor_custom_split_topic_title' ) ) {
    function tutor_custom_split_topic_title( $title ) {
        $clean_title = str_replace(
            array( "\xc2\xa0", "\xa0", "&nbsp;", "\r", "\n" ),
            ' ',
            $title
        );

        $clean_title = html_entity_decode( $clean_title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

        // \p{Pd} = qualquer tipo de traco (hifen, meia-risca, travessao...)
        $parts = preg_split( '/\s*\p{Pd}\s*/u', $clean_title, 2 );

        if ( is_array( $parts ) && count( $parts ) === 2 ) {
            return array(
                'area' => trim( $parts[0] ),
                'text' => trim( $parts[1] ),
            );
        }

        return array(
            'area' => trim( $title ),
            'text' => trim( $title ),
        );
    }
}

/**
 * Extrai o ID de um item retornado por get_contents_by_topic().
 */
if ( ! function_exists( 'tutor_custom_item_id' ) ) {
    function tutor_custom_item_id( $item ) {
        if ( is_object( $item ) && isset( $item->ID ) ) {
            return (int) $item->ID;
        }
        if ( is_array( $item ) && isset( $item['ID'] ) ) {
            return (int) $item['ID'];
        }
        return 0;
    }
}

// ==========================================================================
// 2. NAVEGACAO, MODO E RECURSOS
// ==========================================================================
$nav_contents = tutor_utils()->get_course_prev_next_contents_by_id( $content_id );
$previous_id  = $nav_contents->previous_id;
$next_id      = $nav_contents->next_id;
$topic_id     = $post->post_parent;

// *** CHAVE MESTRA: define o layout de toda a licao ***
$is_exam_mode = tutor_custom_topic_is_exam( $topic_id );

// Divisao dinamica do conteudo por delimitadores
$raw_content = apply_filters( 'the_content', $post->post_content );

$left_content       = '';
$right_content      = '';
$tutorial_content   = '';
$resolution_content = '';

$parts_resolution   = explode( '<!--resolution-->', $raw_content );
$main_and_tutorial  = $parts_resolution[0];
$resolution_content = isset( $parts_resolution[1] ) ? trim( $parts_resolution[1] ) : '';

$parts_tutorial   = explode( '<!--tutorial-->', $main_and_tutorial );
$main_split       = $parts_tutorial[0];
$tutorial_content = isset( $parts_tutorial[1] ) ? trim( $parts_tutorial[1] ) : '';

$parts_main    = explode( '<!--split-->', $main_split );
$left_content  = isset( $parts_main[0] ) ? trim( $parts_main[0] ) : '';
$right_content = isset( $parts_main[1] ) ? trim( $parts_main[1] ) : '';

// A tela dividida depende APENAS do delimitador <!--split-->,
// independentemente do modo da licao.
$has_right_pane = ( '' !== $right_content );

// ==========================================================================
// VIDEO DA AULA (aba "Video" do Tutor LMS)
// ==========================================================================
$lesson_video_html = '';
$video_info        = tutor_utils()->get_video_info( $content_id );

if ( $video_info ) {
    ob_start();

    if ( function_exists( 'tutor_lesson_video' ) ) {
        // Player nativo do Tutor (respeita source, poster e tempo de reproducao)
        tutor_lesson_video( false );
    }

    $lesson_video_html = trim( ob_get_clean() );

    // Fallback: se o Tutor nao imprimiu nada, monta o embed pela URL salva
    if ( '' === $lesson_video_html ) {
        $video_source = isset( $video_info->source ) ? $video_info->source : '';
        $source_key   = 'source_' . $video_source;
        $video_url    = isset( $video_info->{$source_key} ) ? $video_info->{$source_key} : '';

        if ( $video_url ) {
            if ( in_array( $video_source, array( 'youtube', 'vimeo', 'embedded', 'external_url' ), true ) ) {
                $embed = wp_oembed_get( $video_url );
                $lesson_video_html = $embed ? $embed : '<video src="' . esc_url( $video_url ) . '" controls playsinline></video>';
            } else {
                $lesson_video_html = '<video src="' . esc_url( $video_url ) . '" controls playsinline></video>';
            }
        }
    }

    if ( '' !== $lesson_video_html ) {
        $lesson_video_html = '<div class="lesson-video-wrapper">' . $lesson_video_html . '</div>';
    }
}

// Converte URLs soltas em players incorporados
if ( ! function_exists( 'tutor_custom_maybe_embed' ) ) {
    function tutor_custom_maybe_embed( $content ) {
        $clean = trim( wp_strip_all_tags( $content ) );

        if ( filter_var( $clean, FILTER_VALIDATE_URL ) ) {
            $embed = wp_oembed_get( $clean );
            if ( $embed ) {
                return $embed;
            }
        }

        global $wp_embed;
        if ( $wp_embed instanceof WP_Embed ) {
            $content = $wp_embed->autoembed( $content );
        }

        return $content;
    }
}

$tutorial_content   = tutor_custom_maybe_embed( $tutorial_content );
$resolution_content = tutor_custom_maybe_embed( $resolution_content );

// ==========================================================================
// ANEXOS DA AULA (aba "Anexos" do editor do Tutor)
// ==========================================================================
$lesson_attachments = array();
if ( method_exists( tutor_utils(), 'get_attachments' ) ) {
    $raw_attachments = tutor_utils()->get_attachments( $content_id );
    if ( ! empty( $raw_attachments ) && is_array( $raw_attachments ) ) {
        $lesson_attachments = $raw_attachments;
    }
}

// ==========================================================================
// ANOTACOES DO ALUNO NESTA AULA
// ==========================================================================
$lesson_notes = array();
if ( $user_id ) {
    $stored_notes = get_user_meta( $user_id, '_tutor_custom_notes_' . $content_id, true );
    if ( is_array( $stored_notes ) ) {
        $lesson_notes = $stored_notes;
    }
}

// Grade de questoes: só faz sentido no modo simulado
$lessons_query = null;
if ( $is_exam_mode ) {
    $lessons_query = new WP_Query( array(
        'post_type'      => 'lesson',
        'post_parent'    => $topic_id,
        'posts_per_page' => -1,
        'orderby'        => 'menu_order',
        'order'          => 'ASC',
    ) );
}

// Link do botao "Painel": dashboard do aluno no Tutor LMS
$dashboard_url = method_exists( tutor_utils(), 'tutor_dashboard_url' )
    ? tutor_utils()->tutor_dashboard_url()
    : home_url( '/dashboard/' );

get_tutor_header( true );
?>

<!-- ==========================================================================
     3. FOLHA DE ESTILOS CSS
     ========================================================================== -->
<style>
/* Layout Estrutural */
.custom-course-container {
    display: flex;
    flex-direction: column;
    height: 100vh;
    font-family: sans-serif;
    background-color: #ffffff;
}

/* Header */
.custom-course-header {
    background-color: #1a1e68;
    color: #ffffff;
    padding: 15px 30px;
    display: flex;
    flex-wrap: wrap;
    justify-content: space-between;
    align-items: center;
    gap: 20px;
}

/* Header enxuto (modo aula) */
.custom-course-header.is-lesson-mode {
    padding: 12px 30px;
}

.custom-course-header.is-lesson-mode .lesson-title-header {
    font-size: 17px;
    font-weight: bold;
    margin: 0;
    color: #ffffff !important;
    line-height: 1.3;
}

.header-left-block {
    display: flex;
    align-items: center;
	width: 40%;
}

/* Botao de Toggle do Menu Lateral */
.sidebar-toggle-btn {
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #ffffff;
    padding: 8px 16px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
    font-weight: bold;
    transition: background 0.2s, border-color 0.2s;
    display: flex;
    align-items: center;
    gap: 8px;
    margin-right: 1rem;
}

.sidebar-toggle-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: #ffffff;
}

.lessons-grid-wrapper {
    display: flex;
    flex-direction: column;
    gap: 5px;
    max-width: 100%;
}

.lessons-grid-title {
    font-size: 13px;
    opacity: 0.8;
}

.lessons-grid {
    display: grid;
    gap: 6px;
    max-width: 100%;
    grid-template-columns: repeat(10, 1fr);
    grid-auto-rows: auto;
}

.lesson-num-btn {
    display: flex;
    justify-content: center;
    align-items: center;
    width: 2vw;
    height: 2vw;
    background-color: #ffffff;
    color: #333;
    text-decoration: none;
    font-weight: bold;
    border-radius: 4px;
    font-size: 14px;
    transition: opacity 0.2s;
}

.lesson-num-btn:hover {
    opacity: 0.9;
}

.lesson-num-btn.active {
    background-color: #0066ff !important;
    color: #ffffff !important;
}
.lesson-num-btn.completed {
    background-color: #1cbd55 !important;
    color: #ffffff !important;
}
.lesson-num-btn.in-progress {
    background-color: #ffcc00 !important;
    color: #000000 !important;
}

/* Metadata e Cronometro */
.header-right-meta {
    display: flex;
    align-items: center;
	justify-content: space-between;
	width: 58%;
}

.student-info {
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.student-name {
    display: block;
    font-size: 1.25rem;
    font-weight: 300;
}

.timer-container {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 5px;
}

.timer {
    font-size: 3rem;
    font-weight: bold;
    line-height: 1;
}

.timer-controls {
    display: flex;
    gap: 5px;
}

.timer-ctrl-btn {
    background: rgba(255, 255, 255, 0.15);
    border: none;
    color: #ffffff;
	width: 2.5vw;
    height: 2.5vw;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    justify-content: center;
    align-items: center;
    font-size: 11px;
    transition: background 0.2s;
}

.timer-ctrl-btn:hover {
    background: rgba(255, 255, 255, 0.3);
}

.timer-ctrl-btn.is-paused {
    background: #ffcc00;
    color: #000;
    animation: blinkPause 1s infinite alternate;
}

@keyframes blinkPause {
    0% { opacity: 1; }
    100% { opacity: 0.6; }
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 20px;
}

/* Botoes de ferramentas da aula */
.header-tools {
    display: flex;
    align-items: center;
    gap: 8px;
}

.tool-btn {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 7px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: #ffffff;
    padding: .5rem 1rem;
    border-radius: 20px;
    cursor: pointer;
    font-size: .75rem;
    font-weight: bold;
    line-height: 1;
    transition: background 0.2s, border-color 0.2s;
}

.tool-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: #ffffff;
}

/* Botao-link (Painel): mesmo visual dos botoes */
a.tool-btn,
a.tool-btn:hover,
a.tool-btn:focus {
    color: #ffffff;
    text-decoration: none;
}

.tool-btn.is-primary {
    background: #0066ff;
    border-color: #0066ff;
}

.tool-btn.is-primary:hover {
    background: #0052cc;
}

.tool-ico {
    font-size: 14px;
}

.tool-badge {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    min-width: 18px;
    height: 18px;
    padding: 0 5px;
    border-radius: 9px;
    background: #ffcc00;
    color: #000;
    font-size: 11px;
}

/* Lista de anexos */
.attachment-list,
.note-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.attachment-list li,
.note-list li {
    border-bottom: 1px solid #eef1f5;
}

.attachment-list a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 20px;
    color: #1a1e68;
    text-decoration: none;
    font-size: 14px;
}

.attachment-list a:hover {
    background: #edf2fc;
    color: #0066ff;
}

.attachment-size {
    margin-left: auto;
    font-size: 12px;
    color: #8a93a2;
}

/* Lista de anotacoes */
.note-list li {
    padding: 14px 20px;
}

.note-text {
    margin: 0 0 6px;
    font-size: 14px;
    line-height: 1.5;
    color: #333;
    white-space: pre-wrap;
}

.note-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 12px;
    color: #8a93a2;
}

.note-delete {
    background: none;
    border: none;
    color: #d33;
    cursor: pointer;
    font-size: 12px;
    padding: 0;
}

.panel-empty {
    padding: 30px 20px;
    text-align: center;
    color: #8a93a2;
    font-size: 14px;
}

/* Painel de leitura (anexos/anotacoes) rola o conteudo */
.slideout-panel.is-scrollable .panel-body {
    aspect-ratio: auto;
    overflow-y: auto;
    padding: 0;
}

/* Modal de anotacao */
.note-modal-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(10, 14, 40, 0.45);
    z-index: 10000;
    display: none;
}

.note-modal-backdrop.is-open {
    display: block;
}

.note-modal {
    position: fixed;
    top: 90px;
    right: 30px;
    width: 420px;
    max-width: calc(100vw - 40px);
    background: #ffffff;
    border-radius: 10px;
    box-shadow: 0 12px 40px rgba(0, 0, 0, 0.25);
    z-index: 10001;
    display: none;
    overflow: hidden;
}

.note-modal.is-open {
    display: block;
}

.note-modal-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 18px;
    border-bottom: 1px solid #eef1f5;
    font-weight: bold;
    color: #1a1e68;
}

.note-modal-body {
    padding: 16px 18px;
}

.note-modal-body textarea {
    width: 100%;
    min-height: 120px;
    padding: 12px;
    border: 1px solid #c7d2e2;
    border-radius: 6px;
    font-family: inherit;
    font-size: 14px;
    line-height: 1.5;
    resize: vertical;
    outline: none;
}

.note-modal-body textarea:focus {
    border-color: #0066ff;
    box-shadow: 0 0 0 3px rgba(0, 102, 255, 0.12);
}

.note-modal-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 10px;
    padding: 0 18px 16px;
}

.note-modal-footer button {
    border: none;
    border-radius: 6px;
    padding: 9px 20px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
}

.note-cancel {
    background: none;
    color: #5a6478;
}

.note-save {
    background: #0066ff;
    color: #fff;
}

.note-save:disabled {
    opacity: 0.6;
    cursor: default;
}

.note-feedback {
    margin-right: auto;
    font-size: 12.5px;
    color: #1cbd55;
}

.finish-btn-wrapper .tutor-btn {
    background-color: #ffffff !important;
    color: #1a1e68 !important;
    border-radius: 20px !important;
    padding: .5rem .5rem !important;
    font-weight: bold !important;
    border: none !important;
}

.finish-btn-wrapper .tutor-btn span{
	font-size: .75rem;
}

.nav-arrows {
    display: flex;
    gap: 10px;
	align-items: center;
}

.arrow-btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
	width: 2vw;
	height: 2vw;
    border: 2px solid #fff;
    border-radius: 50%;
    color: #fff;
    text-decoration: none;
    font-weight: bold;
    font-size: 1rem;
}

.arrow-btn.disabled {
    opacity: 0.3;
    pointer-events: none;
}

/* Area de Trabalho Central */
.custom-course-main-wrapper {
    display: flex;
    flex: 1;
    overflow: hidden;
    position: relative;
}

/* Menu Lateral Retratil */
.custom-course-sidebar {
    width: 290px;
    background-color: #f4f6f9;
    border-right: 1px solid #e1e6eb;
    overflow-y: auto;
    transition: width 0.3s cubic-bezier(0.16, 1, 0.3, 1),
                padding 0.3s cubic-bezier(0.16, 1, 0.3, 1),
                opacity 0.2s ease;
    opacity: 1;
}

/* Estado Fechado do Menu Lateral */
.custom-course-main-wrapper.sidebar-closed .custom-course-sidebar {
    width: 0px;
    padding-left: 0px;
    padding-right: 0px;
    opacity: 0;
    pointer-events: none;
    border-right: none;
}

.sidebar-title {
    font-size: 16px;
    margin-bottom: 1px;
    color: #ff7e00 !important;
    font-weight: bold;
    white-space: nowrap;
    background: linear-gradient(45deg, #1E1763, #241d7a);
    padding: 10px;
}

.grouped-menu-accordion {
    display: flex;
    flex-direction: column;
    gap: 1px;
    min-width: 250px;
}

.menu-group {
    overflow: hidden;
    background: linear-gradient(45deg, #1E1763, #241d7a);
}

.menu-group-header {
    width: 100%;
    background: linear-gradient(45deg, #1E1763, #241d7a);
    border: none;
    padding: 12px 15px;
    text-align: left;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    font-weight: bold;
    color: #fff;
    transition: background 0.2s;
    gap: 10px;
}

.group-arrow {
    font-size: 10px;
    transition: transform 0.2s;
    flex-shrink: 0;
}

.menu-group.is-open .group-arrow {
    transform: rotate(180deg);
}

.menu-group-items {
    list-style: none;
    margin: 0;
    padding: 0;
    display: none;
    border-top: 1px solid #e1e6eb;
    background-color: #fcfdfe;
}

.menu-group.is-open .menu-group-items {
    display: block;
}

.menu-item-link {
    border-bottom: 1px solid #f0f2f5;
}

.menu-item-link:last-child {
    border-bottom: none;
}

.menu-item-link a {
    display: block;
    padding: 11px 20px;
    color: #555;
    text-decoration: none;
    font-size: 13.5px;
    line-height: 1.4;
}

.menu-item-link a:hover {
    background-color: #edf2fc;
    color: #0066ff;
}

.menu-item-link.is-active {
    background-color: #e4ecfa;
    border-left: 4px solid #0066ff;
}

.menu-item-link.is-active a {
    font-weight: bold;
    color: #0066ff;
}

/* Marcacao de licao concluida no menu lateral */
.menu-item-link.is-done > a::before {
    content: "\2713";
    color: #1cbd55;
    font-weight: bold;
    background: #b3fdb6;
    aspect-ratio: 1 / 1;
    border-radius: 100%;
    height: 15px;
    width: 15px;
    justify-content: center;
    display: inline-flex;
}

/* Divisor de tela (Split) */
.custom-course-body {
    display: flex;
    flex: 1;
    overflow: hidden;
    height: 100%;
}

.body-pane {
    flex: 1;
    overflow-y: auto;
    padding: 30px;
    height: 100%;
}

.pane-left {
    background-color: #ffffff;
}

.pane-right {
    background-color: #f8f9fa;
}

.pane-content {
    max-width: 100%;
}

/* Video da aula (aba Video do Tutor) */
.lesson-video-wrapper {
    margin-bottom: 25px;
    border-radius: 8px;
    overflow: hidden;
    background: #000;
}

.lesson-video-wrapper iframe,
.lesson-video-wrapper video,
.lesson-video-wrapper .plyr,
.lesson-video-wrapper .tutor-video-player {
    display: block;
    width: 100%;
    aspect-ratio: 16 / 9;
    height: auto;
    border: 0;
}

.pane-divider {
    width: 2px;
    background-color: #1a1e68;
    align-self: stretch;
}

/* Modo aula: painel unico e centralizado */
.custom-course-body.is-single-pane .pane-left {
    max-width: 1100px;
    margin: 0 auto;
    padding: 40px 30px;
}

/* Gatilhos de abas laterais */
.slideout-triggers-container {
    position: fixed;
    right: 0;
    top: 50%;
    transform: translateY(-50%);
    display: flex;
    flex-direction: column;
    gap: 10px;
    z-index: 1000;
}

.slideout-tab-btn {
    background-color: #1a1e68;
    color: #fff;
    border: 1px solid #ffffff;
    border-right: none;
    padding: 12px 16px;
    border-radius: 0 8px 8px 0;
    cursor: pointer;
    font-weight: bold;
    writing-mode: vertical-rl;
    transform: rotate(180deg);
    transition: background 0.2s;
}

.slideout-tab-btn:hover {
    background-color: #0066ff;
}

/* Paineis deslizantes */
.slideout-panel {
    --panel-width: calc(50vh * 0.85 * 16 / 9);
    position: fixed;
    right: calc(-1 * var(--panel-width));
    width: var(--panel-width);
    top: 25%;
    height: 50%;
    background-color: #ffffff;
    box-shadow: -5px 0 25px rgba(0, 0, 0, 0.15);
    transition: right 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    z-index: 9999;
    display: flex;
    flex-direction: column;
    border-radius: 25px 0 0 25px;
    overflow: hidden;
}

.slideout-panel.is-open {
    right: 0;
}

.panel-header {
    background-color: #1a1e68;
    color: #fff;
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    height: 15%;
}

.panel-header h4 {
    margin: 0;
    font-size: 18px;
    color: #fff;
}

.panel-close-btn {
    background: none;
    border: none;
    color: #fff;
    font-size: 28px;
    cursor: pointer;
    line-height: 1;
}

.panel-body {
    color: #333;
    aspect-ratio: 16 / 9;
    height: 85%;
}

.panel-body iframe,
.panel-body video {
    height: auto;
    width: 100%;
    aspect-ratio: 16 / 9;
}

/* ==========================================================================
   GUIA DE INTEGRACAO (ONBOARDING TOUR)
   ========================================================================== */
.tool-btn.is-tour {
    background: rgba(255, 204, 0, 0.18);
    border-color: rgba(255, 204, 0, 0.6);
}

.tool-btn.is-tour:hover {
    background: rgba(255, 204, 0, 0.32);
    border-color: #ffcc00;
}

/* Modal de boas-vindas */
.tour-welcome-backdrop {
    position: fixed;
    inset: 0;
    background: rgba(10, 14, 40, 0.65);
    z-index: 100001;
    display: none;
    align-items: center;
    justify-content: center;
    padding: 16px;
}

.tour-welcome-backdrop.is-open {
    display: flex;
}

.tour-welcome {
    width: 100%;
    max-width: 460px;
    background: #ffffff;
    border-radius: 14px;
    overflow: hidden;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.35);
    animation: tourPop 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes tourPop {
    0%   { opacity: 0; transform: scale(0.94) translateY(8px); }
    100% { opacity: 1; transform: none; }
}

.tour-welcome-head {
    background: linear-gradient(45deg, #1E1763, #241d7a);
    color: #ffffff;
    padding: 26px 24px 20px;
}

.tour-welcome-icon {
    font-size: 36px;
    line-height: 1;
    margin-bottom: 10px;
}

.tour-welcome-head h3 {
    margin: 0;
    font-size: 20px;
    color: #ffffff !important;
}

.tour-welcome-head span {
    display: block;
    margin-top: 4px;
    font-size: 13px;
    color: #ff7e00;
    font-weight: bold;
}

.tour-welcome-body {
    padding: 20px 26px 8px;
    color: #444;
    font-size: 14.5px;
    line-height: 1.6;
}

.tour-welcome-body p {
    margin: 0 0 12px;
}

.tour-welcome-hint {
    font-size: 13px;
    color: #8a93a2;
}

.tour-welcome-actions {
    display: flex;
    justify-content: center;
    gap: 10px;
    padding: 6px 24px 24px;
}

.tour-btn-primary,
.tour-btn-ghost {
    border-radius: 20px;
    padding: 10px 22px;
    font-size: 14px;
    font-weight: bold;
    cursor: pointer;
    line-height: 1.2;
    transition: background 0.2s, border-color 0.2s, color 0.2s;
}

.tour-btn-primary {
    background: #0066ff;
    border: 1px solid #0066ff;
    color: #ffffff;
}

.tour-btn-primary:hover {
    background: #0052cc;
    border-color: #0052cc;
}

.tour-btn-ghost {
    background: #ffffff;
    border: 1px solid #c7d2e2;
    color: #5a6478;
}

.tour-btn-ghost:hover {
    border-color: #1a1e68;
    color: #1a1e68;
}

.tour-btn-ghost:disabled {
    opacity: 0.4;
    cursor: default;
}

/* Camada do tour: escurece a tela e bloqueia cliques */
.tour-layer {
    position: fixed;
    inset: 0;
    z-index: 100000;
    display: none;
}

.tour-layer.is-active {
    display: block;
}

/* Recorte iluminado sobre o elemento explicado */
.tour-spotlight {
    position: fixed;
    border-radius: 10px;
    box-shadow: 0 0 0 3px #ffcc00, 0 0 0 9999px rgba(10, 14, 40, 0.72);
    pointer-events: none;
    transition: top 0.35s cubic-bezier(0.16, 1, 0.3, 1),
                left 0.35s cubic-bezier(0.16, 1, 0.3, 1),
                width 0.35s cubic-bezier(0.16, 1, 0.3, 1),
                height 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.tour-spotlight.is-empty {
    box-shadow: 0 0 0 9999px rgba(10, 14, 40, 0.72);
}

/* Tooltip */
.tour-tooltip {
    position: fixed;
    top: 0;
    left: 0;
    width: 370px;
    max-width: calc(100vw - 32px);
    background: #ffffff;
    border-radius: 12px;
    box-shadow: 0 16px 48px rgba(0, 0, 0, 0.35);
    padding: 20px 20px 16px;
    color: #333;
    opacity: 0;
    transform: translateY(6px);
    transition: opacity 0.2s, transform 0.2s;
    pointer-events: none;
}

.tour-tooltip.is-visible {
    opacity: 1;
    transform: none;
    pointer-events: auto;
}

.tour-tooltip-arrow {
    display: none;
    position: absolute;
    width: 14px;
    height: 14px;
    background: #ffffff;
    transform: rotate(45deg);
}

.tour-tooltip-arrow.is-bottom { display: block; top: -7px; }
.tour-tooltip-arrow.is-top    { display: block; bottom: -7px; }
.tour-tooltip-arrow.is-right  { display: block; left: -7px; }
.tour-tooltip-arrow.is-left   { display: block; right: -7px; }

.tour-close {
    position: absolute;
    top: 10px;
    right: 12px;
    background: none;
    border: none;
    padding: 0;
    font-size: 24px;
    line-height: 1;
    color: #8a93a2;
    cursor: pointer;
}

.tour-close:hover {
    color: #1a1e68;
}

.tour-step-count {
    display: block;
    font-size: 11.5px;
    font-weight: bold;
    color: #0066ff;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.tour-tooltip h4.tour-title {
    margin: 6px 28px 10px 0;
    font-size: 17px;
    line-height: 1.3;
    color: #1a1e68 !important;
}

.tour-tooltip-body {
    font-size: 14px;
    line-height: 1.6;
    color: #444;
    max-height: 48vh;
    overflow-y: auto;
}

.tour-tooltip-body p {
    margin: 0 0 9px;
}

.tour-tooltip-body p:last-child {
    margin-bottom: 0;
}

.tour-list {
    margin: 0 0 9px;
    padding-left: 18px;
}

.tour-list li {
    margin-bottom: 2px;
}

.tour-legend {
    list-style: none;
    margin: 4px 0 0;
    padding: 0;
    display: grid;
    gap: 7px;
}

.tour-legend li {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13.5px;
}

.tour-chip {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: bold;
    border: 1px solid #d5dbe5;
}

.tour-chip.is-white  { background: #ffffff; color: #333; }
.tour-chip.is-yellow { background: #ffcc00; color: #000; border-color: #ffcc00; }
.tour-chip.is-green  { background: #1cbd55; color: #fff; border-color: #1cbd55; }
.tour-chip.is-blue   { background: #0066ff; color: #fff; border-color: #0066ff; }

.tour-key {
    display: inline-block;
    padding: 0 6px;
    border-radius: 4px;
    background: #1a1e68;
    color: #ffffff;
    font-size: 12px;
    font-weight: bold;
    line-height: 20px;
}

.tour-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-top: 16px;
}

.tour-dots {
    display: flex;
    flex-wrap: wrap;
    gap: 4px;
}

.tour-dot {
    width: 6px;
    height: 6px;
    border-radius: 3px;
    background: #d5dbe5;
    transition: width 0.2s, background 0.2s;
}

.tour-dot.is-done {
    background: #9bb8f0;
}

.tour-dot.is-active {
    width: 16px;
    background: #0066ff;
}

.tour-nav {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
}

.tour-nav .tour-btn-primary,
.tour-nav .tour-btn-ghost {
    padding: 8px 16px;
    font-size: 13px;
}

/* Responsividade */
@media (max-width: 992px) {
    .custom-course-main-wrapper {
        flex-direction: column;
    }
    .custom-course-sidebar {
        width: 100% !important;
        border-right: none;
        border-bottom: 1px solid #e1e6eb;
        max-height: 250px;
    }
    .custom-course-main-wrapper.sidebar-closed .custom-course-sidebar {
        height: 0px !important;
        max-height: 0px !important;
        padding-top: 0px;
        padding-bottom: 0px;
    }
    .custom-course-body {
        height: auto;
    }
    .lessons-grid {
        grid-template-columns: repeat(5, 1fr);
    }
}

@media (max-width: 768px) {
    .custom-course-header {
        padding: 15px;
    }
    .lesson-num-btn {
        font-size: 12px !important;
    }
    .timer {
        font-size: 30px;
    }
}
                                
@media screen and (max-width: 1280px){
	.header-actions{
		flex-direction: column;
	}
}

/* ==========================================================================
   CELULAR NA HORIZONTAL: header compacto e layout igual ao desktop
   (tela baixa e deitada; vem depois das regras acima para sobrescreve-las)
   ========================================================================== */
@media screen and (orientation: landscape) and (max-height: 500px) {
    .custom-course-header,
    .custom-course-header.is-lesson-mode {
        padding: 6px 12px;
        gap: 10px;
        flex-wrap: nowrap;
    }

    .header-left-block {
        width: auto;
        flex-shrink: 0;
    }

    .sidebar-toggle-btn {
        padding: 6px 10px;
        font-size: 12px;
        margin-right: 10px;
    }

    .custom-course-header.is-lesson-mode .lesson-title-header {
        font-size: 14px;
    }

    /* Grade de questoes: 10 por linha, como no desktop */
    .lessons-grid-title {
        display: none;
    }

    .lessons-grid {
        grid-template-columns: repeat(10, 1fr);
        gap: 3px;
    }

    .lesson-num-btn,
    .lesson-num-btn[class] {
        width: 18px;
        height: 18px;
        font-size: 10px !important;
        border-radius: 3px;
    }

    .header-right-meta {
        width: auto;
        flex: 1;
        min-width: 0;
        gap: 8px;
    }

    /* Nome numa linha so + cronometro menor */
    .student-name {
        font-size: 11px;
        max-width: 115px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .timer-container {
        gap: 6px;
        margin-top: 2px;
    }

    .timer {
        font-size: 24px;
    }

    .timer-ctrl-btn {
        width: 20px;
        height: 20px;
        font-size: 8px;
    }

    .header-actions {
        gap: 5px;
        align-items: flex-end;
    }

    .header-tools {
        gap: 5px;
    }

    /* Ferramentas so com icone (o texto continua no title do botao) */
    .tool-btn {
        padding: 5px 8px;
        gap: 4px;
    }

    .tool-btn .tool-label {
        display: none;
    }

    .tool-ico {
        font-size: 12px;
    }

    .nav-arrows {
        gap: 6px;
    }

    .finish-btn-wrapper .tutor-btn {
        padding: 4px 8px !important;
        white-space: nowrap;
    }

    .finish-btn-wrapper .tutor-btn span {
        font-size: 10px;
    }

    .arrow-btn {
        width: 22px;
        height: 22px;
        font-size: 11px;
    }

    /* Menu lateral como gaveta ao lado do conteudo (como no desktop) */
    .custom-course-main-wrapper {
        flex-direction: row;
    }

    .custom-course-sidebar {
        width: 230px !important;
        max-height: none;
        border-bottom: none;
        border-right: 1px solid #e1e6eb;
    }

    .custom-course-main-wrapper.sidebar-closed .custom-course-sidebar {
        width: 0px !important;
        height: auto !important;
        max-height: none !important;
        border-right: none;
    }

    .grouped-menu-accordion {
        min-width: 210px;
    }

    .custom-course-body {
        height: 100%;
    }

    .body-pane {
        padding: 16px;
    }

    /* Gavetas laterais ocupam mais da altura, que e pequena */
    .slideout-panel {
        --panel-width: min(calc(80vh * 0.85 * 16 / 9), 90vw);
        top: 10%;
        height: 80%;
    }

    .panel-header {
        padding: 10px 16px;
    }

    .panel-header h4 {
        font-size: 15px;
    }

    .slideout-tab-btn {
        padding: 8px 8px;
        font-size: 12px;
    }

    /* Modal de boas-vindas do guia cabe na altura da tela */
    .tour-welcome {
        max-height: calc(100vh - 32px);
        overflow-y: auto;
    }

    .tour-welcome-head {
        padding: 14px 20px 12px;
    }

    .tour-welcome-icon {
        font-size: 24px;
        margin-bottom: 4px;
    }
}

@media screen and (orientation: landscape) and (max-height: 500px) and (max-width: 640px) {
    .student-name {
        display: none;
    }
}

/* ==========================================================================
   CELULAR/TABLET NA VERTICAL: bloqueia o curso e pede para girar o aparelho
   (so em telas de toque, para nao bloquear uma janela estreita no computador)
   ========================================================================== */
.rotate-lock {
    display: none;
}

@media screen and (orientation: portrait) and (max-width: 1024px) and (hover: none) and (pointer: coarse) {
    html,
    body {
        overflow: hidden !important;
    }

    .rotate-lock {
        position: fixed;
        inset: 0;
        z-index: 2147483000;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 24px;
        background: linear-gradient(45deg, #1E1763, #241d7a);
        color: #ffffff;
        font-family: sans-serif;
        text-align: center;
    }

    .rotate-lock-box {
        max-width: 320px;
    }

    .rotate-lock-icon {
        display: inline-block;
        font-size: 64px;
        line-height: 1;
        margin-bottom: 20px;
        animation: rotateHint 2s ease-in-out infinite;
    }

    .rotate-lock h3 {
        margin: 0 0 10px;
        font-size: 22px;
        color: #ffffff !important;
    }

    .rotate-lock p {
        margin: 0;
        font-size: 15px;
        line-height: 1.6;
        color: rgba(255, 255, 255, 0.85);
    }
}

@keyframes rotateHint {
    0%, 20%   { transform: rotate(0deg); }
    50%, 80%  { transform: rotate(-90deg); }
    100%      { transform: rotate(0deg); }
}
</style>

<!-- ==========================================================================
     4. ESTRUTURA VISUAL HTML
     ========================================================================== -->
<div class="custom-course-container"
     data-course-id="<?php echo esc_attr( $course_id ); ?>"
     data-topic-id="<?php echo esc_attr( $topic_id ); ?>"
     data-current-lesson-id="<?php echo esc_attr( $content_id ); ?>"
     data-exam-mode="<?php echo $is_exam_mode ? '1' : '0'; ?>">

    <!-- HEADER -->
    <header class="custom-course-header <?php echo $is_exam_mode ? 'is-exam-mode' : 'is-lesson-mode'; ?>">

        <div class="header-left-block">
            <!-- Botao Menu Retratil (sempre presente) -->
            <button id="btn-toggle-sidebar" class="sidebar-toggle-btn" title="Mostrar/Ocultar Menu Lateral">
                <span>&#9776; Menu</span>
            </button>

            <?php if ( $is_exam_mode && $lessons_query && $lessons_query->have_posts() ) : ?>
                <!-- Grade de Questoes: SOMENTE no modo simulado -->
                <div class="lessons-grid-wrapper">
                    <span class="lessons-grid-title">Questões:</span>
                    <div class="lessons-grid">
                        <?php
                        $count = 1;
                        while ( $lessons_query->have_posts() ) : $lessons_query->the_post();
                            $loop_lesson_id = get_the_ID();
                            $is_current     = ( $loop_lesson_id === $content_id );
                            $is_completed   = tutor_utils()->is_completed_lesson( $loop_lesson_id, $user_id );

                            $class = 'lesson-num-btn';
                            if ( $is_current ) {
                                $class .= ' active';
                            } elseif ( $is_completed ) {
                                $class .= ' completed';
                            }
                            ?>
                            <a href="<?php the_permalink(); ?>"
                               class="<?php echo esc_attr( $class ); ?>"
                               data-lesson-id="<?php echo esc_attr( $loop_lesson_id ); ?>">
                                <?php echo (int) $count; ?>
                            </a>
                            <?php
                            $count++;
                        endwhile;
                        wp_reset_postdata();
                        ?>
                    </div>
                </div>
            <?php else : ?>
                <!-- Modo aula: apenas o titulo da licao -->
                <h2 class="lesson-title-header"><?php echo esc_html( get_the_title( $content_id ) ); ?></h2>
            <?php endif; ?>
        </div>

        <div class="header-right-meta">

            <?php if ( $is_exam_mode ) : ?>
                <!-- Aluno + Cronometro: SOMENTE no modo simulado -->
                <div class="student-info">
                    <span class="student-name"><?php echo esc_html( $current_user->display_name ); ?></span>

                    <div class="timer-container">
                        <div id="countdown-timer" class="timer">--:--</div>
                        <div class="timer-controls">
                            <button id="btn-timer-pause" class="timer-ctrl-btn" title="Pausar/Retomar">&#10074;&#10074;</button>
                            <button id="btn-timer-reset" class="timer-ctrl-btn" title="Reiniciar">&#10227;</button>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="header-actions">

                <!-- Ferramentas da aula: Painel / Guia / Anexos / Anotacoes / Anotar -->
                <div class="header-tools">
                    <a href="<?php echo esc_url( $dashboard_url ); ?>" id="btn-dashboard" class="tool-btn" title="Ir para o meu painel">
                        <span class="tool-ico">&#127968;</span>
                        <span class="tool-label">Painel</span>
                    </a>

                    <?php if ( $tour_has_button ) : ?>
                        <button type="button" id="btn-start-tour" class="tool-btn is-tour" title="Ver o guia desta tela">
                            <span class="tool-ico">&#129517;</span>
                            <span class="tool-label">Guia</span>
                        </button>
                    <?php endif; ?>

                    <button type="button" class="tool-btn" data-open-panel="panel-attachments" title="Anexos da aula">
                        <span class="tool-ico">&#128206;</span>
                        <span class="tool-label">Anexos</span>
                        <?php if ( ! empty( $lesson_attachments ) ) : ?>
                            <span class="tool-badge"><?php echo count( $lesson_attachments ); ?></span>
                        <?php endif; ?>
                    </button>

                    <button type="button" class="tool-btn" data-open-panel="panel-notes" title="Minhas anotações">
                        <span class="tool-ico">&#128196;</span>
                        <span class="tool-label">Anotações</span>
                        <span class="tool-badge js-notes-count" <?php echo empty( $lesson_notes ) ? 'style="display:none"' : ''; ?>><?php echo count( $lesson_notes ); ?></span>
                    </button>

                    <button type="button" id="btn-take-note" class="tool-btn is-primary" title="Escrever uma anotação">
                        <span class="tool-ico">&#9998;</span>
                        <span class="tool-label">Anotar</span>
                    </button>
                </div>

                <div class="nav-arrows">
                    <div class="finish-btn-wrapper">
                    	<?php tutor_lesson_mark_complete_html(); ?>
                	</div>
                                
                    <?php if ( $previous_id ) : ?>
                        <a href="<?php echo esc_url( get_permalink( $previous_id ) ); ?>" class="arrow-btn btn-prev">&lt;</a>
                    <?php else : ?>
                        <span class="arrow-btn disabled">&lt;</span>
                    <?php endif; ?>

                    <?php if ( $next_id ) : ?>
                        <a href="<?php echo esc_url( get_permalink( $next_id ) ); ?>" class="arrow-btn btn-next">&gt;</a>
                    <?php else : ?>
                        <span class="arrow-btn disabled">&gt;</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </header>

    <!-- AREA CENTRAL COM MENU LATERAL E CORPO -->
    <div class="custom-course-main-wrapper">

        <!-- MENU LATERAL -->
        <aside class="custom-course-sidebar">
            <h3 class="sidebar-title">Navegação</h3>
            <div class="grouped-menu-accordion">
                <?php
                $topics       = tutor_utils()->get_topics( $course_id );
                $menu_blocks  = array();  // blocos na ordem de exibicao
                $area_index   = array();  // area => indice em $menu_blocks

                if ( $topics && $topics->have_posts() ) {
                    while ( $topics->have_posts() ) {
                        $topics->the_post();
                        $loop_topic_id = get_the_ID();
                        $topic_title   = get_the_title();
                        $topic_is_exam = tutor_custom_topic_is_exam( $loop_topic_id );
                        // Simulados e orientacoes [orientacao] entram no menu agrupado por area
                        $topic_in_area = $topic_is_exam || tutor_custom_topic_is_grouped( $loop_topic_id );

                        $topic_contents = tutor_utils()->get_contents_by_topic( $loop_topic_id );
                        if ( ! is_array( $topic_contents ) ) {
                            $topic_contents = array();
                        }

                        if ( $topic_in_area ) {
                            // ---- SIMULADO / ORIENTACAO: agrupa por area, aponta para a 1a licao ----
                            $split     = tutor_custom_split_topic_title( $topic_title );
                            $area_name = $split['area'];
                            $text_name = $split['text'];

                            $first_lesson_url = '#';
                            if ( ! empty( $topic_contents ) ) {
                                $first_id = tutor_custom_item_id( $topic_contents[0] );
                                if ( $first_id ) {
                                    $first_lesson_url = get_permalink( $first_id );
                                }
                            }

                            // Simulado concluido = todas as atividades do topico concluidas
                            $topic_done = ! empty( $topic_contents );
                            foreach ( $topic_contents as $item ) {
                                $item_id = tutor_custom_item_id( $item );
                                if ( $item_id && ! tutor_utils()->is_completed_lesson( $item_id, $user_id ) ) {
                                    $topic_done = false;
                                    break;
                                }
                            }

                            if ( ! isset( $area_index[ $area_name ] ) ) {
                                $area_index[ $area_name ] = count( $menu_blocks );
                                $menu_blocks[]            = array(
                                    'type'  => 'area',
                                    'label' => $area_name,
                                    'open'  => false,
                                    'items' => array(),
                                );
                            }

                            $idx = $area_index[ $area_name ];

                            $menu_blocks[ $idx ]['items'][] = array(
                                'label'  => $text_name,
                                'url'    => $first_lesson_url,
                                'active' => ( (int) $loop_topic_id === (int) $topic_id ),
                                'done'   => $topic_done,
                            );

                            if ( (int) $loop_topic_id === (int) $topic_id ) {
                                $menu_blocks[ $idx ]['open'] = true;
                            }
                        } else {
                            // ---- MODO AULA: topico proprio, listando cada licao ----
                            $items     = array();
                            $block_open = false;

                            foreach ( $topic_contents as $item ) {
                                $item_id = tutor_custom_item_id( $item );
                                if ( ! $item_id ) {
                                    continue;
                                }

                                $is_current_item = ( $item_id === (int) $content_id );
                                if ( $is_current_item ) {
                                    $block_open = true;
                                }

                                $items[] = array(
                                    'label'  => get_the_title( $item_id ),
                                    'url'    => get_permalink( $item_id ),
                                    'active' => $is_current_item,
                                    'done'   => tutor_utils()->is_completed_lesson( $item_id, $user_id ) ? true : false,
                                );
                            }

                            $menu_blocks[] = array(
                                'type'  => 'topic',
                                'label' => tutor_custom_strip_exam_flag( $topic_title ),
                                'open'  => $block_open,
                                'items' => $items,
                            );
                        }
                    }
                    wp_reset_postdata();
                }

                // Renderiza os blocos
                foreach ( $menu_blocks as $block ) :
                    if ( empty( $block['items'] ) ) {
                        continue;
                    }
                    ?>
                    <div class="menu-group<?php echo $block['open'] ? ' is-open' : ''; ?>">
                        <button class="menu-group-header">
                            <span class="group-title"><?php echo esc_html( $block['label'] ); ?></span>
                            <span class="group-arrow">&#9662;</span>
                        </button>
                        <ul class="menu-group-items">
                            <?php foreach ( $block['items'] as $item ) :
                                $li_class = 'menu-item-link';
                                if ( ! empty( $item['active'] ) ) {
                                    $li_class .= ' is-active';
                                }
                                if ( ! empty( $item['done'] ) ) {
                                    $li_class .= ' is-done';
                                }
                                ?>
                                <li class="<?php echo esc_attr( $li_class ); ?>">
                                    <a href="<?php echo esc_url( $item['url'] ); ?>">
                                        <?php echo esc_html( $item['label'] ); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php
                endforeach;
                ?>
            </div>
        </aside>

        <!-- CORPO DA LICAO -->
        <main class="custom-course-body<?php echo $has_right_pane ? '' : ' is-single-pane'; ?>">
            <section class="body-pane pane-left">
                <div class="pane-content">
                    <?php
                    if ( '' !== $lesson_video_html ) {
                        echo $lesson_video_html;
                    }
                    echo $left_content;
                    ?>
                </div>
            </section>

            <?php if ( $has_right_pane ) : ?>
                <div class="pane-divider"></div>

                <section class="body-pane pane-right">
                    <div class="pane-content">
                        <?php echo $right_content; ?>
                    </div>
                </section>
            <?php endif; ?>
        </main>
    </div>
</div>

<!-- ==========================================================================
     5. GATILHOS E PAINEIS LATERAIS (SLIDE-OUT TABS)
     ========================================================================== -->
<div class="slideout-triggers-container">
    <?php if ( ! empty( $tutorial_content ) ) : ?>
        <button class="slideout-tab-btn" data-panel="panel-tutorial">Dica</button>
    <?php endif; ?>
    <?php if ( ! empty( $resolution_content ) ) : ?>
        <button class="slideout-tab-btn" data-panel="panel-resolution">Resolução</button>
    <?php endif; ?>
</div>

<!-- Painel de Tutorial -->
<?php if ( ! empty( $tutorial_content ) ) : ?>
<div id="panel-tutorial" class="slideout-panel">
    <div class="panel-header">
        <h4>Tutorial / Dica</h4>
        <button class="panel-close-btn">&times;</button>
    </div>
    <div class="panel-body">
        <?php echo $tutorial_content; ?>
    </div>
</div>
<?php endif; ?>

<!-- Painel de Resolucao -->
<?php if ( ! empty( $resolution_content ) ) : ?>
<div id="panel-resolution" class="slideout-panel">
    <div class="panel-header">
        <h4>Resolução Comentada</h4>
        <button class="panel-close-btn">&times;</button>
    </div>
    <div class="panel-body">
        <?php echo $resolution_content; ?>
    </div>
</div>
<?php endif; ?>

<!-- Painel de Anexos -->
<div id="panel-attachments" class="slideout-panel is-scrollable">
    <div class="panel-header">
        <h4>Anexos da aula</h4>
        <button class="panel-close-btn">&times;</button>
    </div>
    <div class="panel-body">
        <?php if ( ! empty( $lesson_attachments ) ) : ?>
            <ul class="attachment-list">
                <?php foreach ( $lesson_attachments as $attachment ) :
                    $att_url  = isset( $attachment->url ) ? $attachment->url : '';
                    $att_name = isset( $attachment->name ) ? $attachment->name : basename( $att_url );
                    $att_size = isset( $attachment->size ) ? $attachment->size : '';
                    if ( ! $att_url ) {
                        continue;
                    }
                    ?>
                    <li>
                        <a href="<?php echo esc_url( $att_url ); ?>" target="_blank" rel="noopener" download>
                            <span>&#128206;</span>
                            <span><?php echo esc_html( $att_name ); ?></span>
                            <?php if ( $att_size ) : ?>
                                <span class="attachment-size"><?php echo esc_html( $att_size ); ?></span>
                            <?php endif; ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else : ?>
            <p class="panel-empty">Esta aula não possui anexos.</p>
        <?php endif; ?>
    </div>
</div>

<!-- Painel de Anotacoes -->
<div id="panel-notes" class="slideout-panel is-scrollable">
    <div class="panel-header">
        <h4>Minhas anotações</h4>
        <button class="panel-close-btn">&times;</button>
    </div>
    <div class="panel-body">
        <ul class="note-list js-note-list">
            <?php foreach ( $lesson_notes as $index => $note ) :
                $note_text = isset( $note['text'] ) ? $note['text'] : '';
                $note_date = isset( $note['date'] ) ? $note['date'] : '';
                ?>
                <li data-note-index="<?php echo esc_attr( $index ); ?>">
                    <p class="note-text"><?php echo esc_html( $note_text ); ?></p>
                    <div class="note-meta">
                        <span><?php echo esc_html( $note_date ); ?></span>
                        <button type="button" class="note-delete">Excluir</button>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="panel-empty js-notes-empty" <?php echo empty( $lesson_notes ) ? '' : 'style="display:none"'; ?>>
            Você ainda não fez anotações nesta aula.
        </p>
    </div>
</div>

<!-- Modal de Anotacao -->
<div id="note-modal-backdrop" class="note-modal-backdrop"></div>
<div id="note-modal" class="note-modal">
    <div class="note-modal-header">
        <span>&#9998;</span>
        <span>Anotar</span>
    </div>
    <div class="note-modal-body">
        <textarea id="note-input" placeholder="Escreva sua anotação para consultar depois"></textarea>
    </div>
    <div class="note-modal-footer">
        <span class="note-feedback js-note-feedback"></span>
        <button type="button" class="note-cancel">Cancelar</button>
        <button type="button" class="note-save">Salvar</button>
    </div>
</div>

<!-- ==========================================================================
     GUIA DE INTEGRACAO (ONBOARDING TOUR)
     ========================================================================== -->
<?php if ( $tour_is_auto ) : ?>
<!-- Modal de boas-vindas: pergunta se o aluno quer ver o guia -->
<div id="tour-welcome" class="tour-welcome-backdrop" role="dialog" aria-modal="true" aria-labelledby="tour-welcome-title">
    <div class="tour-welcome">
        <div class="tour-welcome-head">
            <div class="tour-welcome-icon">&#129517;</div>
            <h3 id="tour-welcome-title">Olá, <?php echo esc_html( $current_user->display_name ); ?>!</h3>
            <span>Bem-vindo(a) ao preparatório TEAP</span>
        </div>
        <div class="tour-welcome-body">
            <p>Preparamos um tour rápido mostrando cada parte desta tela: navegação, questões, cronômetro, anotações, resolução e dicas. Leva menos de 2 minutos.</p>
            <?php if ( $tour_has_button ) : ?>
                <p class="tour-welcome-hint">Você pode rever o guia a qualquer momento pelo botão <strong>Guia</strong> no topo da página.</p>
            <?php endif; ?>
        </div>
        <div class="tour-welcome-actions">
            <button type="button" class="tour-btn-ghost js-tour-skip">Pular</button>
            <button type="button" class="tour-btn-primary js-tour-start">Ver tutorial</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ( $tour_enabled ) : ?>
<!-- Camada do tour: overlay + recorte + tooltip -->
<div id="tour-layer" class="tour-layer" aria-hidden="true">
    <div class="tour-spotlight"></div>
    <div class="tour-tooltip" role="dialog" aria-live="polite" aria-labelledby="tour-title">
        <span class="tour-tooltip-arrow"></span>
        <button type="button" class="tour-close" title="Fechar guia" aria-label="Fechar guia">&times;</button>
        <span class="tour-step-count"></span>
        <h4 id="tour-title" class="tour-title"></h4>
        <div class="tour-tooltip-body"></div>
        <div class="tour-footer">
            <div class="tour-dots"></div>
            <div class="tour-nav">
                <button type="button" class="tour-btn-ghost tour-prev">Anterior</button>
                <button type="button" class="tour-btn-primary tour-next">Próximo</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Bloqueio da orientacao vertical no celular/tablet (exibido so via CSS) -->
<div class="rotate-lock" role="alertdialog" aria-labelledby="rotate-lock-title">
    <div class="rotate-lock-box">
        <div class="rotate-lock-icon">&#128241;</div>
        <h3 id="rotate-lock-title">Gire o seu aparelho</h3>
        <p>Este curso foi feito para ser usado na horizontal, igual à tela da prova. Vire o celular ou tablet para continuar.</p>
    </div>
</div>

<!-- ==========================================================================
     6. SCRIPT JAVASCRIPT
     ========================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var container = document.querySelector('.custom-course-container');
    if (!container) return;

    var topicId         = container.getAttribute('data-topic-id');
    var currentLessonId = container.getAttribute('data-current-lesson-id');
    var isExamMode      = container.getAttribute('data-exam-mode') === '1';

    // Configuracao do guia (definida antes do cronometro: se o modal de
    // boas-vindas for aparecer, o cronometro ja nasce segurado)
    var tourConfig = <?php echo wp_json_encode( array(
        'autoStart'  => $tour_is_auto,
        'showOnce'   => (bool) $tour_auto_show_once,
        'storageKey' => 'teap_tour_seen_' . $content_id,
    ) ); ?>;

    var tourWillPrompt = false;
    if (tourConfig.autoStart && document.getElementById('tour-welcome')) {
        var tourSeenBefore = false;
        try { tourSeenBefore = localStorage.getItem(tourConfig.storageKey) === '1'; } catch (e) {}
        tourWillPrompt = !tourConfig.showOnce || !tourSeenBefore;
    }

    // Controle do cronometro usado pelo guia (sem efeito fora do modo simulado)
    var examTimer = { hold: function() {}, release: function() {} };

    // ==========================================
    // A. CRONOMETRO (SOMENTE NO MODO SIMULADO)
    // ==========================================
    var timerKey = 'exam_end_time_topic_' + topicId;
    var pauseKey = 'exam_paused_time_topic_' + topicId;
    var holdKey  = 'exam_tour_hold_topic_' + topicId; // pausa feita pelo guia (nao pelo aluno)
    var seenKey  = 'exam_last_seen_topic_' + topicId; // ultima vez que uma pagina deste simulado esteve aberta

    if (isExamMode) {
        var durationMinutes = 90;
        var display  = document.querySelector('#countdown-timer');
        var btnPause = document.querySelector('#btn-timer-pause');
        var btnReset = document.querySelector('#btn-timer-reset');

        var storedEnd     = localStorage.getItem(timerKey);
        var endTime       = storedEnd ? parseInt(storedEnd, 10) : 0;
        var pausedOffset  = localStorage.getItem(pauseKey);
        var timerInterval = null;

        function resetTimer() {
            endTime = Date.now() + (durationMinutes * 60 * 1000);
            localStorage.setItem(timerKey, endTime);
            localStorage.removeItem(pauseKey);
            localStorage.removeItem(holdKey);
            pausedOffset = null;

            if (btnPause) {
                btnPause.innerHTML = "&#10074;&#10074;";
                btnPause.classList.remove('is-paused');
            }
            if (display) {
                display.style.color = "";
            }
        }

        function markSeen() {
            localStorage.setItem(seenKey, Date.now());
        }

        function updateTimer() {
            var remainingTime;

            markSeen();
            pausedOffset = localStorage.getItem(pauseKey);

            if (pausedOffset) {
                remainingTime = parseInt(pausedOffset, 10) * 1000;
            } else {
                remainingTime = endTime - Date.now();
            }

            if (remainingTime <= 0) {
                if (display) {
                    display.textContent = "00:00";
                    display.style.color = "#ff0000";
                }
                clearInterval(timerInterval);
                return;
            }

            var totalSeconds = Math.floor(remainingTime / 1000);
            var minutes = Math.floor(totalSeconds / 60);
            var seconds = totalSeconds % 60;

            minutes = minutes < 10 ? "0" + minutes : minutes;
            seconds = seconds < 10 ? "0" + seconds : seconds;

            if (display) {
                display.textContent = minutes + ":" + seconds;
            }
        }

        function startInterval() {
            clearInterval(timerInterval);
            timerInterval = setInterval(updateTimer, 1000);
        }

        if (btnPause) {
            btnPause.addEventListener('click', function() {
                pausedOffset = localStorage.getItem(pauseKey);

                if (pausedOffset) {
                    endTime = Date.now() + (parseInt(pausedOffset, 10) * 1000);
                    localStorage.setItem(timerKey, endTime);
                    localStorage.removeItem(pauseKey);
                    btnPause.innerHTML = "&#10074;&#10074;";
                    btnPause.classList.remove('is-paused');
                } else {
                    var secondsLeft = Math.floor((endTime - Date.now()) / 1000);
                    if (secondsLeft > 0) {
                        localStorage.setItem(pauseKey, secondsLeft);
                        btnPause.innerHTML = "&#9658;";
                        btnPause.classList.add('is-paused');
                    }
                }
                updateTimer();
            });
        }

        if (btnReset) {
            btnReset.addEventListener('click', function() {
                if (confirm('Tem certeza que deseja reiniciar o tempo da prova?')) {
                    resetTimer();
                    updateTimer();
                    startInterval();
                }
            });
        }

        // Segura o cronometro enquanto o guia estiver aberto. Se o aluno ja
        // tinha pausado por conta propria, nada muda (e nada e retomado depois).
        function holdTimer() {
            if (localStorage.getItem(holdKey) || localStorage.getItem(pauseKey)) return;

            var secondsLeft = Math.floor((endTime - Date.now()) / 1000);
            if (secondsLeft <= 0) return;

            localStorage.setItem(pauseKey, secondsLeft);
            localStorage.setItem(holdKey, '1');
            updateTimer();
        }

        // Retoma o cronometro somente se quem pausou foi o guia
        function releaseTimer() {
            if (!localStorage.getItem(holdKey)) return;
            localStorage.removeItem(holdKey);

            var heldSeconds = localStorage.getItem(pauseKey);
            if (heldSeconds) {
                endTime = Date.now() + (parseInt(heldSeconds, 10) * 1000);
                localStorage.setItem(timerKey, endTime);
                localStorage.removeItem(pauseKey);
            }
            updateTimer();
        }

        examTimer = { hold: holdTimer, release: releaseTimer };

        // O tempo so corre enquanto o aluno esta neste simulado: ao voltar
        // (de outro simulado, de uma aula avulsa ou com o navegador fechado),
        // devolve o intervalo em que nenhuma pagina dele esteve aberta.
        var lastSeen = parseInt(localStorage.getItem(seenKey) || '0', 10);
        if (endTime && lastSeen && endTime > lastSeen && !pausedOffset) {
            var awayMs = Date.now() - lastSeen;
            if (awayMs > 0) {
                endTime += awayMs;
                localStorage.setItem(timerKey, endTime);
            }
        }

        // Registra a saida da pagina para o calculo acima
        window.addEventListener('pagehide', markSeen);
        document.addEventListener('visibilitychange', markSeen);

        // Primeira visita ao topico (ou chaves limpas): inicia a contagem.
        // Com o modal do guia, ja comeca segurado nos 90:00 completos.
        if (!endTime && !pausedOffset) {
            if (tourWillPrompt) {
                localStorage.setItem(pauseKey, durationMinutes * 60);
                localStorage.setItem(holdKey, '1');
            } else {
                resetTimer();
            }
        }

        if (tourWillPrompt) {
            // Modal de boas-vindas vai abrir: o tempo so corre depois de
            // "Pular" ou de concluir o guia
            holdTimer();
        } else {
            // Sobra de um guia fechado sem concluir (ex.: aba fechada no meio)
            releaseTimer();
        }

        updateTimer();
        startInterval();
    }

    // Limpa chaves do cronometro ao concluir licao
    var finishBtn = document.querySelector('.tutor-btn-complete-lesson');
    if (finishBtn) {
        finishBtn.addEventListener('click', function() {
            localStorage.removeItem(timerKey);
            localStorage.removeItem(pauseKey);
            localStorage.removeItem(holdKey);
            localStorage.removeItem(seenKey);
        });
    }

    // ==========================================
    // B. MENU LATERAL RETRATIL (DRAWER) COM PERSISTENCIA
    // ==========================================
    var btnToggleSidebar = document.querySelector('#btn-toggle-sidebar');
    var mainWrapper      = document.querySelector('.custom-course-main-wrapper');

    if (mainWrapper && localStorage.getItem('sidebar_closed') === 'true') {
        mainWrapper.classList.add('sidebar-closed');
    }

    if (btnToggleSidebar && mainWrapper) {
        btnToggleSidebar.addEventListener('click', function() {
            mainWrapper.classList.toggle('sidebar-closed');
            localStorage.setItem('sidebar_closed', mainWrapper.classList.contains('sidebar-closed'));
        });
    }

    // Accordion do menu
    document.querySelectorAll('.menu-group-header').forEach(function(header) {
        header.addEventListener('click', function() {
            this.parentElement.classList.toggle('is-open');
        });
    });

    // Rola ate o item ativo
    var activeItem = document.querySelector('.menu-item-link.is-active');
    if (activeItem) {
        var parentGroup = activeItem.closest('.menu-group');
        if (parentGroup) {
            parentGroup.classList.add('is-open');
        }
        if (typeof activeItem.scrollIntoView === 'function') {
            activeItem.scrollIntoView({ block: 'nearest' });
        }
    }

    // ==========================================
    // C. ABAS LATERAIS COM CONTROLE DE PAUSA DE MIDIA
    // ==========================================
    var tabButtons   = document.querySelectorAll('.slideout-tab-btn');
    var panels       = document.querySelectorAll('.slideout-panel');
    var closeButtons = document.querySelectorAll('.panel-close-btn');

    function stopVideosInPanel(panel) {
        panel.querySelectorAll('video').forEach(function(video) {
            video.pause();
        });

        panel.querySelectorAll('iframe').forEach(function(iframe) {
            var src = iframe.src;
            iframe.src = '';
            iframe.src = src;
        });
    }

    tabButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var targetPanel = document.getElementById(this.getAttribute('data-panel'));
            if (!targetPanel) return;
            togglePanel(targetPanel);
        });
    });

    // Botoes do header que abrem painEis (Anexos / Anotacoes)
    document.querySelectorAll('[data-open-panel]').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            var targetPanel = document.getElementById(this.getAttribute('data-open-panel'));
            if (!targetPanel) return;
            togglePanel(targetPanel);
        });
    });

    function togglePanel(targetPanel) {
        if (targetPanel.classList.contains('is-open')) {
            targetPanel.classList.remove('is-open');
            stopVideosInPanel(targetPanel);
        } else {
            panels.forEach(function(p) {
                p.classList.remove('is-open');
                stopVideosInPanel(p);
            });
            targetPanel.classList.add('is-open');
        }
    }

    closeButtons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var panel = this.closest('.slideout-panel');
            panel.classList.remove('is-open');
            stopVideosInPanel(panel);
        });
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.slideout-panel') &&
            !e.target.closest('.slideout-tab-btn') &&
            !e.target.closest('[data-open-panel]') &&
            !e.target.closest('.note-modal')) {
            panels.forEach(function(p) {
                if (p.classList.contains('is-open')) {
                    p.classList.remove('is-open');
                    stopVideosInPanel(p);
                }
            });
        }
    });

    // ==========================================
    // E. MODAL DE ANOTACAO
    // ==========================================
    var noteModal    = document.getElementById('note-modal');
    var noteBackdrop = document.getElementById('note-modal-backdrop');
    var noteInput    = document.getElementById('note-input');
    var btnTakeNote  = document.getElementById('btn-take-note');
    var noteList     = document.querySelector('.js-note-list');
    var noteEmpty    = document.querySelector('.js-notes-empty');
    var noteCount    = document.querySelector('.js-notes-count');
    var noteFeedback = document.querySelector('.js-note-feedback');
    var ajaxUrl      = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
    var noteNonce    = '<?php echo esc_js( wp_create_nonce( 'tutor_custom_notes' ) ); ?>';

    function openNoteModal() {
        if (!noteModal) return;
        noteModal.classList.add('is-open');
        noteBackdrop.classList.add('is-open');
        if (noteFeedback) noteFeedback.textContent = '';
        noteInput.focus();
    }

    function closeNoteModal() {
        if (!noteModal) return;
        noteModal.classList.remove('is-open');
        noteBackdrop.classList.remove('is-open');
    }

    if (btnTakeNote) {
        btnTakeNote.addEventListener('click', function(e) {
            e.stopPropagation();
            openNoteModal();
        });
    }

    if (noteBackdrop) {
        noteBackdrop.addEventListener('click', closeNoteModal);
    }

    var noteCancel = document.querySelector('.note-cancel');
    if (noteCancel) {
        noteCancel.addEventListener('click', closeNoteModal);
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeNoteModal();
    });

    function refreshNotesUi() {
        var total = noteList ? noteList.querySelectorAll('li').length : 0;

        if (noteCount) {
            noteCount.textContent = total;
            noteCount.style.display = total ? '' : 'none';
        }
        if (noteEmpty) {
            noteEmpty.style.display = total ? 'none' : '';
        }
    }

    function appendNote(text, date, index) {
        if (!noteList) return;

        var li = document.createElement('li');
        li.setAttribute('data-note-index', index);

        var p = document.createElement('p');
        p.className = 'note-text';
        p.textContent = text;

        var meta = document.createElement('div');
        meta.className = 'note-meta';

        var when = document.createElement('span');
        when.textContent = date;

        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'note-delete';
        del.textContent = 'Excluir';

        meta.appendChild(when);
        meta.appendChild(del);
        li.appendChild(p);
        li.appendChild(meta);
        noteList.appendChild(li);

        refreshNotesUi();
    }

    function sendNoteRequest(action, payload, onDone) {
        var body = new URLSearchParams();
        body.append('action', action);
        body.append('nonce', noteNonce);
        body.append('lesson_id', currentLessonId);

        Object.keys(payload).forEach(function(key) {
            body.append(key, payload[key]);
        });

        fetch(ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        })
        .then(function(res) { return res.json(); })
        .then(onDone)
        .catch(function() {
            if (noteFeedback) {
                noteFeedback.style.color = '#d33';
                noteFeedback.textContent = 'Erro ao salvar.';
            }
        });
    }

    var noteSave = document.querySelector('.note-save');
    if (noteSave) {
        noteSave.addEventListener('click', function() {
            var text = noteInput.value.trim();
            if (!text) {
                noteInput.focus();
                return;
            }

            noteSave.disabled = true;

            sendNoteRequest('tutor_custom_save_note', { note: text }, function(data) {
                noteSave.disabled = false;

                if (data && data.success) {
                    appendNote(text, data.data.date, data.data.index);
                    noteInput.value = '';
                    if (noteFeedback) {
                        noteFeedback.style.color = '#1cbd55';
                        noteFeedback.textContent = 'Anotação salva.';
                    }
                    setTimeout(closeNoteModal, 700);
                } else if (noteFeedback) {
                    noteFeedback.style.color = '#d33';
                    noteFeedback.textContent = 'Não foi possível salvar.';
                }
            });
        });
    }

    if (noteList) {
        noteList.addEventListener('click', function(e) {
            var btn = e.target.closest('.note-delete');
            if (!btn) return;

            var li = btn.closest('li');
            var index = li.getAttribute('data-note-index');

            sendNoteRequest('tutor_custom_delete_note', { index: index }, function(data) {
                if (data && data.success) {
                    li.remove();
                    // Reindexa os itens restantes para casar com o servidor
                    noteList.querySelectorAll('li').forEach(function(item, i) {
                        item.setAttribute('data-note-index', i);
                    });
                    refreshNotesUi();
                }
            });
        });
    }

    refreshNotesUi();

    // ==========================================
    // D. PROGRESSO DE ATIVIDADE & RASTREAMENTO H5P
    // ==========================================
    if (isExamMode) {
        document.querySelectorAll('.lesson-num-btn').forEach(function(btn) {
            var btnLessonId = btn.getAttribute('data-lesson-id');
            var status = localStorage.getItem('lesson_status_' + btnLessonId);

            if (status === 'in-progress' && !btn.classList.contains('active') && !btn.classList.contains('completed')) {
                btn.classList.add('in-progress');
            }
        });
    }

    function markCurrentLessonInProgress() {
        localStorage.setItem('lesson_status_' + currentLessonId, 'in-progress');

        var currentBtn = document.querySelector('.lesson-num-btn[data-lesson-id="' + currentLessonId + '"]');
        if (currentBtn && !currentBtn.classList.contains('completed') && !currentBtn.classList.contains('active')) {
            currentBtn.classList.add('in-progress');
        }
    }

    // O H5P roda dentro de um iframe (.new-h5p-iframe), entao o
    // window.H5P da pagina nao existe: procuramos o H5P em cada iframe
    // (inclusive iframes aninhados) e escutamos o xAPI de la.
    var H5P_IFRAME_SELECTOR = '.new-h5p-iframe, iframe.h5p-iframe, iframe[src*="h5p"]';

    function onH5PStatement(event) {
        var statement = (event && event.data && event.data.statement) ? event.data.statement : null;
        if (!statement || !statement.verb) return;

        var verbId = statement.verb.id || '';
        var verbDisplay = (statement.verb.display && statement.verb.display['en-US']) ? statement.verb.display['en-US'] : '';

        if (verbId.indexOf('/interacted') !== -1 || verbDisplay === 'interacted') {
            markCurrentLessonInProgress();
        }
    }

    // Liga o listener no H5P de uma janela. Retorna false se a janela
    // for de outro dominio (sem acesso) ou se o H5P ainda nao carregou.
    function bindH5PInWindow(win) {
        try {
            var dispatcher = win && win.H5P && win.H5P.externalDispatcher;
            if (!dispatcher) return false;
            if (!dispatcher.__teapTracked) {
                dispatcher.__teapTracked = true;
                dispatcher.on('xAPI', onH5PStatement);
            }
            return true;
        } catch (e) {
            return false;
        }
    }

    // Percorre a pagina e os iframes acessiveis procurando o H5P
    function scanH5PFrames(doc, depth) {
        var found = false;
        var frames;
        try {
            frames = doc.querySelectorAll('iframe');
        } catch (e) {
            return false;
        }

        Array.prototype.forEach.call(frames, function(frame) {
            var win = frame.contentWindow;
            var isH5PFrame = frame.matches(H5P_IFRAME_SELECTOR);

            if (bindH5PInWindow(win)) {
                found = true;
                frame.setAttribute('data-teap-h5p', 'tracked');
                return;
            }

            var innerDoc = null;
            try { innerDoc = frame.contentDocument; } catch (e) {}

            if (innerDoc) {
                if (depth < 3 && scanH5PFrames(innerDoc, depth + 1)) {
                    found = true;
                    frame.setAttribute('data-teap-h5p', 'tracked');
                }
            } else if (isH5PFrame) {
                // Iframe H5P de outro dominio: usa o clique como sinal
                frame.setAttribute('data-teap-h5p', 'click');
            }
        });

        return found || bindH5PInWindow(doc.defaultView);
    }

    // O H5P carrega depois da pagina: tenta por ~30s e de novo a cada
    // iframe que terminar de carregar (o evento load nao sobe, por isso
    // a escuta e na fase de captura)
    var h5pScanTries = 0;
    var h5pScanInterval = setInterval(function() {
        h5pScanTries++;
        if (scanH5PFrames(document, 0) || h5pScanTries >= 30) {
            clearInterval(h5pScanInterval);
        }
    }, 1000);
    scanH5PFrames(document, 0);

    document.addEventListener('load', function(e) {
        if (e.target && e.target.tagName === 'IFRAME') {
            setTimeout(function() { scanH5PFrames(document, 0); }, 300);
        }
    }, true);

    // Plano B para iframe H5P de outro dominio (sem acesso ao xAPI):
    // clicar dentro dele tira o foco da pagina e o iframe vira o elemento ativo
    window.addEventListener('blur', function() {
        setTimeout(function() {
            var active = document.activeElement;
            if (active && active.tagName === 'IFRAME' && active.getAttribute('data-teap-h5p') === 'click') {
                markCurrentLessonInProgress();
            }
        }, 0);
    });

    // ==========================================
    // F. GUIA DE INTEGRACAO (ONBOARDING TOUR)
    // ==========================================
    var tourLayer   = document.getElementById('tour-layer');
    var tourWelcome = document.getElementById('tour-welcome');

    if (tourLayer) {
        var tourSpot   = tourLayer.querySelector('.tour-spotlight');
        var tourTip    = tourLayer.querySelector('.tour-tooltip');
        var tourArrow  = tourLayer.querySelector('.tour-tooltip-arrow');
        var tourCount  = tourLayer.querySelector('.tour-step-count');
        var tourTitle  = tourLayer.querySelector('.tour-title');
        var tourBody   = tourLayer.querySelector('.tour-tooltip-body');
        var tourDots   = tourLayer.querySelector('.tour-dots');
        var tourPrev   = tourLayer.querySelector('.tour-prev');
        var tourNext   = tourLayer.querySelector('.tour-next');
        var btnTour    = document.getElementById('btn-start-tour');
        var tourActive = [];
        var tourIndex  = -1;
        var tourTimer  = null;
        var sidebarWasClosed = false;

        function openSidebarForTour() {
            if (mainWrapper) mainWrapper.classList.remove('sidebar-closed');
        }

        function restoreSidebar() {
            if (mainWrapper && sidebarWasClosed) mainWrapper.classList.add('sidebar-closed');
        }

        // Cada passo: target = seletores destacados juntos (passos cujo
        // elemento nao existe nesta aula sao pulados automaticamente).
        var tourSteps = [
            {
                target: ['#btn-toggle-sidebar', '.custom-course-sidebar'],
                title: 'Menu do curso',
                placement: 'right',
                delay: 380,
                onEnter: openSidebarForTour,
                onLeave: restoreSidebar,
                body:
                    '<p>Aqui está todo o conteúdo do preparatório, dividido por área específica:</p>' +
                    '<ul class="tour-list">' +
                        '<li>Biológicas / Saúde</li>' +
                        '<li>Exatas / Tecnológicas</li>' +
                        '<li>Humanas / Sociais</li>' +
                        '<li>Agrárias / Veterinárias</li>' +
                    '</ul>' +
                    '<p>Cada área possui <strong>5 simulados com o layout o mais próximo possível do exame real</strong>, para você fazer o teste com total segurança.</p>' +
                    '<p>No dia do exame, você escolhe a área que domina, mas aqui trabalhamos <strong>todas</strong>: é muito comum cair textos da sua área com detalhes de outras, e isso fortalece seu conhecimento prévio.</p>'
            },
            {
                target: ['.lessons-grid-wrapper'],
                title: 'Questões do simulado',
                body:
                    '<p>Todo simulado é composto por <strong>20 questões</strong>, listadas aqui. Clique no número para ir direto à questão e acompanhe seu progresso pelas cores:</p>' +
                    '<ul class="tour-legend">' +
                        '<li><span class="tour-chip is-white">1</span> Não vista</li>' +
                        '<li><span class="tour-chip is-yellow">2</span> Em andamento</li>' +
                        '<li><span class="tour-chip is-green">3</span> Concluída</li>' +
                        '<li><span class="tour-chip is-blue">4</span> Questão em que você está agora</li>' +
                    '</ul>'
            },
            {
                target: ['.student-info'],
                title: 'Nome e cronômetro',
                body:
                    '<p>No cabeçalho da prova, mostramos seu nome e o cronômetro com o tempo real do exame: <strong>90 minutos</strong>.</p>' +
                    '<p>Na prova, o tempo é contínuo e, ao zerar, ela se encerra automaticamente. Aqui ele <strong>não fecha</strong> o simulado: serve para você se programar e ter noção do seu aproveitamento.</p>' +
                    '<p>A contagem é <strong>por simulado</strong> e continua correndo enquanto você passa de uma questão para outra. Use <span class="tour-key">&#10074;&#10074;</span> <strong>para pausar</strong>, caso você queira, e <span class="tour-key">&#10227;</span> <strong>para reiniciar</strong> caso queira refazer o simulado.</p>'
            },
            {
                target: ['#btn-dashboard'],
                title: 'Painel',
                body: '<p>Leva você direto para o <strong>seu painel</strong>, onde ficam seus cursos, seu progresso e seus certificados.</p>' +
                    '<p>Se você estiver no meio de um simulado, o cronômetro <strong>fica parado</strong> enquanto estiver fora e continua de onde parou quando você voltar.</p>'
            },
            {
                target: ['[data-open-panel="panel-attachments"]'],
                title: 'Anexos',
                body:
                    '<p>Quando a aula ou o vídeo citar um artigo, documento ou material de apoio, ele aparece aqui para você fazer o download.</p>' +
                    '<p>Repare no <strong>número</strong> ao lado do botão: ele indica quantos arquivos existem. Se não tiver o número, a aula não possui anexos.</p>'
            },
            {
                target: ['[data-open-panel="panel-notes"]'],
                title: 'Anotações',
                body: '<p>Lista com todas as anotações que você fez <strong>nesta aula</strong>. O número ao lado mostra quantas existem.</p>'
            },
            {
                target: ['#btn-take-note'],
                title: 'Anotar',
                body: '<p>Crie anotações sobre o que quiser nesta aula. Elas ficam salvas e aparecem em <strong>Anotações</strong> sempre que você voltar aqui para revisar.</p>'
            },
            {
                target: ['.finish-btn-wrapper'],
                title: 'Marcar como concluído',
                body:
                    '<p>Marque o conteúdo como concluído para não se perder no seu progresso.</p>' +
                    '<p><strong>Importante:</strong> o certificado de conclusão só é liberado quando <strong>todas as aulas</strong> estiverem marcadas como concluídas, ou seja, quando a plataforma registrar 100% do conteúdo.</p>'
            },
            {
                target: ['.nav-arrows .arrow-btn'],
                title: 'Navegação',
                body: '<p>Use as setas para navegar entre as aulas e as questões do simulado: <span class="tour-key">&lt;</span> <strong>volta para a anterior</strong> e <span class="tour-key">&gt;</span> <strong>avança para a próxima.</strong></p>'
            },
            {
                target: ['.custom-course-body:not(.is-single-pane)'],
                title: 'Duas colunas, como na prova',
                body: '<p>Assim como na prova, o simulado é dividido em <strong>duas colunas com rolagem independente</strong>: você rola o texto sem perder a questão de vista, e vice-versa.</p>'
            },
            {
                target: ['.custom-course-body:not(.is-single-pane) .pane-left'],
                title: 'Coluna da esquerda: o texto',
                placement: 'right',
                body: '<p>Aqui fica o <strong>texto</strong> relacionado às questões. Como no teste, a <strong>primeira e a última questão</strong> trazem o texto completo; as demais mostram apenas o trecho necessário para responder.</p>'
            },
            {
                target: ['.pane-right'],
                title: 'Coluna da direita: a questão',
                placement: 'left',
                body: '<p>Aqui fica a <strong>questão</strong> em si: o enunciado e as <strong>4 alternativas</strong> de múltipla escolha.</p>'
            },
            {
                target: ['.slideout-tab-btn[data-panel="panel-resolution"]'],
                title: 'Resolução',
                placement: 'left',
                body: '<p>Abre um <strong>vídeo explicativo</strong> com a resolução da questão em que você está.</p>'
            },
            {
                target: ['.slideout-tab-btn[data-panel="panel-tutorial"]'],
                title: 'Dica',
                placement: 'left',
                body: '<p>Abre um <strong>vídeo com dicas</strong> sobre quais habilidades a questão está exigindo, como elas funcionam e como melhorá-las.</p>'
            },
            {
                target: btnTour ? ['#btn-start-tour'] : null,
                title: 'Tudo pronto!',
                body: btnTour
                    ? '<p>Bons estudos! Sempre que precisar, clique em <strong>Guia</strong> para rever este tutorial.</p>'
                    : '<p>Bons estudos e boa prova!</p>'
            }
        ];

        // Retangulo que envolve todos os elementos visiveis do passo
        function tourRect(selectors) {
            var rect = null;
            selectors.forEach(function(sel) {
                document.querySelectorAll(sel).forEach(function(el) {
                    var r = el.getBoundingClientRect();
                    if (!r.width || !r.height) return;
                    if (!rect) {
                        rect = { top: r.top, left: r.left, right: r.right, bottom: r.bottom };
                    } else {
                        rect.top    = Math.min(rect.top, r.top);
                        rect.left   = Math.min(rect.left, r.left);
                        rect.right  = Math.max(rect.right, r.right);
                        rect.bottom = Math.max(rect.bottom, r.bottom);
                    }
                });
            });
            if (!rect) return null;

            rect.top    = Math.max(rect.top, 0);
            rect.left   = Math.max(rect.left, 0);
            rect.right  = Math.min(rect.right, window.innerWidth);
            rect.bottom = Math.min(rect.bottom, window.innerHeight);
            return rect;
        }

        function clamp(value, min, max) {
            return Math.min(Math.max(value, min), Math.max(min, max));
        }

        function placeTour(step) {
            var vw = window.innerWidth, vh = window.innerHeight;
            var m = 16, gap = 16, pad = 6;
            var r = step.target ? tourRect(step.target) : null;

            if (r) {
                r = { top: r.top - pad, left: r.left - pad, right: r.right + pad, bottom: r.bottom + pad };
                r.width  = r.right - r.left;
                r.height = r.bottom - r.top;
                tourSpot.classList.remove('is-empty');
                tourSpot.style.top    = r.top + 'px';
                tourSpot.style.left   = r.left + 'px';
                tourSpot.style.width  = r.width + 'px';
                tourSpot.style.height = r.height + 'px';
            } else {
                tourSpot.classList.add('is-empty');
                tourSpot.style.top    = (vh / 2) + 'px';
                tourSpot.style.left   = (vw / 2) + 'px';
                tourSpot.style.width  = '0px';
                tourSpot.style.height = '0px';
            }

            var tw = tourTip.offsetWidth, th = tourTip.offsetHeight;
            var place = 'center';

            if (r) {
                var fits = {
                    bottom: vh - r.bottom >= th + gap + m,
                    top:    r.top >= th + gap + m,
                    right:  vw - r.right >= tw + gap + m,
                    left:   r.left >= tw + gap + m
                };
                var order = (step.placement ? [step.placement] : []).concat(['bottom', 'top', 'right', 'left']);
                for (var i = 0; i < order.length; i++) {
                    if (fits[order[i]]) { place = order[i]; break; }
                }
            }

            var top, left;
            if (place === 'bottom') {
                top  = r.bottom + gap;
                left = r.left + r.width / 2 - tw / 2;
            } else if (place === 'top') {
                top  = r.top - gap - th;
                left = r.left + r.width / 2 - tw / 2;
            } else if (place === 'right') {
                left = r.right + gap;
                top  = r.top + r.height / 2 - th / 2;
            } else if (place === 'left') {
                left = r.left - gap - tw;
                top  = r.top + r.height / 2 - th / 2;
            } else {
                // Sem espaco ao redor (ex.: colunas inteiras): tooltip sobre o centro
                left = (r ? r.left + r.width / 2 : vw / 2) - tw / 2;
                top  = (r ? r.top + r.height / 2 : vh / 2) - th / 2;
            }

            left = clamp(left, m, vw - tw - m);
            top  = clamp(top, m, vh - th - m);
            tourTip.style.left = left + 'px';
            tourTip.style.top  = top + 'px';

            tourArrow.className = 'tour-tooltip-arrow' + (place === 'center' ? '' : ' is-' + place);
            tourArrow.style.left = '';
            tourArrow.style.top  = '';
            if (place === 'bottom' || place === 'top') {
                tourArrow.style.left = clamp(r.left + r.width / 2 - left - 7, 16, tw - 30) + 'px';
            } else if (place === 'left' || place === 'right') {
                tourArrow.style.top = clamp(r.top + r.height / 2 - top - 7, 16, th - 30) + 'px';
            }
        }

        function showTourStep(index) {
            var current = tourActive[tourIndex];
            if (current && current.onLeave && index !== tourIndex) current.onLeave();

            tourIndex = index;
            var step  = tourActive[index];
            var total = tourActive.length;

            tourTip.classList.remove('is-visible');
            if (step.onEnter) step.onEnter();

            tourCount.textContent = 'Passo ' + (index + 1) + ' de ' + total;
            tourTitle.textContent = step.title;
            tourBody.innerHTML    = step.body;
            tourBody.scrollTop    = 0;
            tourPrev.disabled     = index === 0;
            tourNext.textContent  = index === total - 1 ? 'Concluir' : 'Próximo';

            tourDots.innerHTML = '';
            for (var i = 0; i < total; i++) {
                var dot = document.createElement('span');
                dot.className = 'tour-dot' + (i === index ? ' is-active' : (i < index ? ' is-done' : ''));
                tourDots.appendChild(dot);
            }

            clearTimeout(tourTimer);
            tourTimer = setTimeout(function() {
                placeTour(step);
                tourTip.classList.add('is-visible');
                tourNext.focus();
            }, step.delay || 200);
        }

        function markTourSeen() {
            try { localStorage.setItem(tourConfig.storageKey, '1'); } catch (e) {}
        }

        function closeTourWelcome() {
            if (tourWelcome) tourWelcome.classList.remove('is-open');
        }

        // Fechar o modal com "Pular" (ou Esc) libera o cronometro
        function skipTourWelcome() {
            closeTourWelcome();
            markTourSeen();
            examTimer.release();
        }

        function startTour() {
            examTimer.hold();
            closeTourWelcome();
            closeNoteModal();
            panels.forEach(function(p) {
                if (p.classList.contains('is-open')) {
                    p.classList.remove('is-open');
                    stopVideosInPanel(p);
                }
            });

            sidebarWasClosed = mainWrapper ? mainWrapper.classList.contains('sidebar-closed') : false;
            tourActive = tourSteps.filter(function(step) {
                return !step.target || tourRect(step.target);
            });
            if (!tourActive.length) {
                examTimer.release();
                return;
            }

            tourIndex = -1;
            tourLayer.classList.add('is-active');
            tourLayer.setAttribute('aria-hidden', 'false');
            showTourStep(0);
        }

        function endTour() {
            var current = tourActive[tourIndex];
            if (current && current.onLeave) current.onLeave();

            clearTimeout(tourTimer);
            tourTip.classList.remove('is-visible');
            tourLayer.classList.remove('is-active');
            tourLayer.setAttribute('aria-hidden', 'true');
            tourIndex = -1;
            markTourSeen();
            examTimer.release();
            if (btnTour) btnTour.focus();
        }

        function nextTourStep() {
            if (tourIndex < tourActive.length - 1) {
                showTourStep(tourIndex + 1);
            } else {
                endTour();
            }
        }

        function prevTourStep() {
            if (tourIndex > 0) showTourStep(tourIndex - 1);
        }

        tourNext.addEventListener('click', nextTourStep);
        tourPrev.addEventListener('click', prevTourStep);
        tourLayer.querySelector('.tour-close').addEventListener('click', endTour);

        if (btnTour) {
            btnTour.addEventListener('click', function(e) {
                e.stopPropagation();
                startTour();
            });
        }

        document.addEventListener('keydown', function(e) {
            if (tourWelcome && tourWelcome.classList.contains('is-open') && e.key === 'Escape') {
                skipTourWelcome();
                return;
            }
            if (tourIndex < 0) return;

            if (e.key === 'Escape') {
                endTour();
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                nextTourStep();
            } else if (e.key === 'ArrowLeft') {
                e.preventDefault();
                prevTourStep();
            }
        });

        window.addEventListener('resize', function() {
            if (tourIndex >= 0) placeTour(tourActive[tourIndex]);
        });

        // Modal de boas-vindas (somente nas aulas configuradas)
        if (tourWelcome) {
            tourWelcome.querySelector('.js-tour-start').addEventListener('click', startTour);
            tourWelcome.querySelector('.js-tour-skip').addEventListener('click', skipTourWelcome);

            if (tourWillPrompt) {
                setTimeout(function() {
                    tourWelcome.classList.add('is-open');
                    tourWelcome.querySelector('.js-tour-start').focus();
                }, 600);
            }
        }
    }
});
</script>

<?php
get_tutor_footer( true );
