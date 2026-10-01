<?php
/**
 * Custom Single Lesson Template - Modo Simulado x Modo Aula
 *
 * Controle central: basta escrever [simulado] na DESCRICAO do topico.
 *  - Topico COM [simulado]  -> header completo (grade de questoes + cronometro)
 *                              e agrupamento por area no menu lateral.
 *  - Topico SEM [simulado]  -> header enxuto (menu, concluir, navegacao)
 *                              e topico listado normalmente com suas licoes.
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
$custom_course_id = 127272; // <-- ALTERE AQUI PARA O ID DO SEU CURSO

if ( $course_id != $custom_course_id ) {
    include tutor()->path . 'templates/single-lesson.php';
    return;
}

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

        $summary = get_post_field( 'post_content', $topic_id );
        $summary = html_entity_decode( (string) $summary, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        $summary = wp_strip_all_tags( $summary );

        // [ espacos? simulado espacos? ] -> case insensitive
        $pattern = '/\[\s*' . preg_quote( TUTOR_CUSTOM_EXAM_FLAG, '/' ) . '\s*\]/iu';

        $cache[ $topic_id ] = (bool) preg_match( $pattern, $summary );

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
    border-bottom: 2px solid #000;
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
    width: 35px;
    height: 35px;
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
    gap: 40px;
}

.student-info {
    text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.student-name {
    display: block;
    font-size: 18px;
    font-weight: 300;
}

.timer-container {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 5px;
}

.timer {
    font-size: 44px;
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
    width: 28px;
    height: 28px;
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
    padding: 8px 14px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 13px;
    font-weight: bold;
    line-height: 1;
    transition: background 0.2s, border-color 0.2s;
}

.tool-btn:hover {
    background: rgba(255, 255, 255, 0.3);
    border-color: #ffffff;
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
    padding: 10px 25px !important;
    font-weight: bold !important;
    border: none !important;
}

.nav-arrows {
    display: flex;
    gap: 10px;
}

.arrow-btn {
    display: inline-flex;
    justify-content: center;
    align-items: center;
    width: 40px;
    height: 40px;
    border: 2px solid #fff;
    border-radius: 50%;
    color: #fff;
    text-decoration: none;
    font-weight: bold;
    font-size: 20px;
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
    padding: 10px;
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
    margin-bottom: 20px;
    color: #1a1e68;
    font-weight: bold;
    white-space: nowrap;
}

.grouped-menu-accordion {
    display: flex;
    flex-direction: column;
    gap: 10px;
    min-width: 250px;
}

.menu-group {
    border-radius: 6px;
    overflow: hidden;
    border: 1px solid #e1e6eb;
    background: #ffffff;
}

.menu-group-header {
    width: 100%;
    background: #ffffff;
    border: none;
    padding: 12px 15px;
    text-align: left;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    font-weight: bold;
    color: #333;
    transition: background 0.2s;
    gap: 10px;
}

.menu-group-header:hover {
    background: #edf0f5;
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
    --panel-width-px: calc(50vh * 0.85 * 16 / 9);
    --panel-width-percent: calc((var(--panel-width-px) / 100vw) * 100%);
    position: fixed;
    right: calc(-1 * var(--panel-width-percent));
    width: var(--panel-width-percent);
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
        width: 30px !important;
        height: 30px !important;
        font-size: 12px !important;
    }
    .timer {
        font-size: 30px;
    }
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

                <!-- Ferramentas da aula: Anexos / Anotacoes / Anotar -->
                <div class="header-tools">
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

                <div class="finish-btn-wrapper">
                    <?php tutor_lesson_mark_complete_html(); ?>
                </div>

                <div class="nav-arrows">
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

                        $topic_contents = tutor_utils()->get_contents_by_topic( $loop_topic_id );
                        if ( ! is_array( $topic_contents ) ) {
                            $topic_contents = array();
                        }

                        if ( $topic_is_exam ) {
                            // ---- MODO SIMULADO: agrupa por area, aponta para a 1a questao ----
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
                                'done'   => false,
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
        <button class="slideout-tab-btn" data-panel="panel-tutorial">Tutorial</button>
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
     6. SCRIPT JAVASCRIPT
     ========================================================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    var container = document.querySelector('.custom-course-container');
    if (!container) return;

    var topicId         = container.getAttribute('data-topic-id');
    var currentLessonId = container.getAttribute('data-current-lesson-id');
    var isExamMode      = container.getAttribute('data-exam-mode') === '1';

    // ==========================================
    // A. CRONOMETRO (SOMENTE NO MODO SIMULADO)
    // ==========================================
    var timerKey = 'exam_end_time_topic_' + topicId;
    var pauseKey = 'exam_paused_time_topic_' + topicId;

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
            pausedOffset = null;

            if (btnPause) {
                btnPause.innerHTML = "&#10074;&#10074;";
                btnPause.classList.remove('is-paused');
            }
            if (display) {
                display.style.color = "";
            }
        }

        function updateTimer() {
            var remainingTime;

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

        // Primeira visita ao topico (ou chaves limpas): inicia a contagem
        if (!endTime && !pausedOffset) {
            resetTimer();
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

    function bindH5PEvents() {
        if (window.H5P && window.H5P.externalDispatcher) {
            window.H5P.externalDispatcher.on('xAPI', function(event) {
                var statement = (event.data && event.data.statement) ? event.data.statement : null;
                if (statement && statement.verb) {
                    var verbId = statement.verb.id || '';
                    var verbDisplay = (statement.verb.display && statement.verb.display['en-US']) ? statement.verb.display['en-US'] : '';

                    if (verbId.indexOf('/interacted') !== -1 || verbDisplay === 'interacted') {
                        markCurrentLessonInProgress();
                    }
                }
            });
        } else {
            setTimeout(bindH5PEvents, 1000);
        }
    }
    bindH5PEvents();
});
</script>

<?php
get_tutor_footer( true );
