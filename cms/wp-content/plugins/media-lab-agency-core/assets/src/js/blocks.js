/**
 * Media Lab – Native Gutenberg Blocks
 *
 * Registriert CTA-Banner, Accordion/FAQ und Icon+Text als native Blocks.
 * Kein JSX – verwendet wp.element.createElement() direkt.
 *
 * Build: Vite → assets/dist/js/blocks.js
 *
 * Fix: wp.domReady()-Wrapper stellt sicher, dass alle wp.*-Globals
 * initialisiert sind, bevor registerBlockType() aufgerufen wird.
 *
 * @since 1.6.0
 */

wp.domReady( function () {

    const { registerBlockType } = wp.blocks;
    const { __ }                = wp.i18n;
    const { createElement: el, Fragment, useState, useEffect, useMemo } = wp.element; // wp.element exportiert kein "el" – Alias auf createElement
    const {
        RichText,
        InspectorControls,
        BlockControls,
        AlignmentToolbar,
        URLInput,
        PanelColorSettings,
        InnerBlocks,
        MediaUpload,
        MediaUploadCheck,
        useBlockProps,
        useInnerBlocksProps,
    } = wp.blockEditor;
    const {
        PanelBody,
        TextControl,
        SelectControl,
        ToggleControl,
        Button,
        Tooltip,
        RangeControl,
    } = wp.components;

    // =============================================================================
    // CTA-Banner
    // =============================================================================

    registerBlockType( 'medialab/cta-banner', {
        edit( { attributes, setAttributes } ) {
            const { title, text, buttonText, buttonUrl, buttonStyle, bgColor, textAlign } = attributes;

            const bgMap = {
                primary:   'var(--color-primary, #ff0000)',
                dark:      'var(--color-dark, #1a1a1a)',
                light:     'var(--color-light, #f5f5f5)',
                white:     '#ffffff',
            };

            const blockProps = useBlockProps( {
                className: `ml-block-cta-banner ml-cta-banner--${bgColor} ml-cta-banner--${textAlign}`,
                style: { backgroundColor: bgMap[ bgColor ] },
            } );

            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: __( 'Einstellungen', 'media-lab-agency-core' ), initialOpen: true },
                        el( SelectControl, {
                            label:    __( 'Hintergrundfarbe', 'media-lab-agency-core' ),
                            value:    bgColor,
                            options:  [
                                { label: 'Primärfarbe', value: 'primary' },
                                { label: 'Dunkel',      value: 'dark' },
                                { label: 'Hell',        value: 'light' },
                                { label: 'Weiß',        value: 'white' },
                            ],
                            onChange: val => setAttributes( { bgColor: val } ),
                        } ),
                        el( SelectControl, {
                            label:    __( 'Button-Stil', 'media-lab-agency-core' ),
                            value:    buttonStyle,
                            options:  [
                                { label: 'Primary', value: 'primary' },
                                { label: 'Outline', value: 'outline' },
                                { label: 'White',   value: 'white' },
                            ],
                            onChange: val => setAttributes( { buttonStyle: val } ),
                        } ),
                    ),
                ),
                el( 'section', blockProps,
                    el( 'div', { className: 'ml-cta-banner__inner container' },
                        el( RichText, {
                            tagName:     'h2',
                            className:   'ml-cta-banner__title',
                            value:       title,
                            onChange:    val => setAttributes( { title: val } ),
                            placeholder: __( 'Titel eingeben…', 'media-lab-agency-core' ),
                        } ),
                        el( RichText, {
                            tagName:     'p',
                            className:   'ml-cta-banner__text',
                            value:       text,
                            onChange:    val => setAttributes( { text: val } ),
                            placeholder: __( 'Beschreibung (optional)…', 'media-lab-agency-core' ),
                        } ),
                        el( 'div', { className: 'ml-cta-banner__actions' },
                            el( RichText, {
                                tagName:     'span',
                                className:   `btn btn--${buttonStyle} ml-cta-banner__btn`,
                                value:       buttonText,
                                onChange:    val => setAttributes( { buttonText: val } ),
                                placeholder: __( 'Button-Text…', 'media-lab-agency-core' ),
                                allowedFormats: [],
                            } ),
                        ),
                        el( 'div', { className: 'ml-cta-banner__url-input' },
                            el( 'label', null, __( 'Button-URL:', 'media-lab-agency-core' ) ),
                            el( URLInput, {
                                value:    buttonUrl,
                                onChange: val => setAttributes( { buttonUrl: val } ),
                            } ),
                        ),
                    ),
                ),
            );
        },

        save( { attributes } ) {
            const { title, text, buttonText, buttonUrl, buttonStyle, bgColor, textAlign } = attributes;
            const blockProps = useBlockProps.save( {
                className: `ml-block-cta-banner ml-cta-banner--${bgColor} ml-cta-banner--${textAlign}`,
            } );
            return el( 'section', blockProps,
                el( 'div', { className: 'ml-cta-banner__inner container' },
                    el( RichText.Content, { tagName: 'h2', className: 'ml-cta-banner__title', value: title } ),
                    text && el( RichText.Content, { tagName: 'p', className: 'ml-cta-banner__text', value: text } ),
                    buttonText && buttonUrl && el( 'div', { className: 'ml-cta-banner__actions' },
                        el( 'a', { href: buttonUrl, className: `btn btn--${buttonStyle} ml-cta-banner__btn` },
                            el( RichText.Content, { value: buttonText } )
                        ),
                    ),
                ),
            );
        },
    } );

    // =============================================================================
    // Accordion / FAQ
    // =============================================================================

    registerBlockType( 'medialab/accordion', {
        edit( { attributes, setAttributes, clientId } ) {
            const { title, allowMultiple } = attributes;

            const blockProps = useBlockProps( { className: 'ml-block-accordion' } );

            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: __( 'Einstellungen', 'media-lab-agency-core' ) },
                        el( ToggleControl, {
                            label:    __( 'Mehrere gleichzeitig öffnen', 'media-lab-agency-core' ),
                            checked:  allowMultiple,
                            onChange: val => setAttributes( { allowMultiple: val } ),
                        } ),
                    ),
                ),
                el( 'div', blockProps,
                    el( RichText, {
                        tagName:     'h2',
                        className:   'ml-accordion__title',
                        value:       title,
                        onChange:    val => setAttributes( { title: val } ),
                        placeholder: __( 'Accordion-Titel (optional)…', 'media-lab-agency-core' ),
                    } ),
                    el( 'p', { className: 'ml-accordion__editor-hint' },
                        __( '→ Accordion-Items über ACF-Felder in der Seitenleiste befüllen.', 'media-lab-agency-core' )
                    ),
                ),
            );
        },

        save( { attributes } ) {
            const { title, allowMultiple } = attributes;
            const blockProps = useBlockProps.save( {
                className: 'ml-block-accordion',
                'data-allow-multiple': allowMultiple ? 'true' : 'false',
            } );
            return el( 'div', blockProps,
                title && el( RichText.Content, { tagName: 'h2', className: 'ml-accordion__title', value: title } ),
                el( 'div', { className: 'ml-accordion__items' }, null ),
            );
        },
    } );

    // =============================================================================
    // Icon + Text
    // =============================================================================

    registerBlockType( 'medialab/icon-text', {
        edit( { attributes, setAttributes } ) {
            const { icon, title, text, iconColor, layout } = attributes;

            const layoutOptions = [
                { label: __( 'Icon oben',  'media-lab-agency-core' ), value: 'top'  },
                { label: __( 'Icon links', 'media-lab-agency-core' ), value: 'left' },
            ];

            const blockProps = useBlockProps( { className: `ml-block-icon-text ml-icon-text--${layout}` } );

            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: __( 'Icon-Einstellungen', 'media-lab-agency-core' ) },
                        el( TextControl, {
                            label:    __( 'Icon (Emoji oder SVG-Klasse)', 'media-lab-agency-core' ),
                            value:    icon,
                            onChange: val => setAttributes( { icon: val } ),
                            help:     __( 'Emoji: ⭐ 🚀 ✅  oder Dashicon: dashicons-star-filled', 'media-lab-agency-core' ),
                        } ),
                        el( TextControl, {
                            label:    __( 'Icon-Farbe (CSS-Wert)', 'media-lab-agency-core' ),
                            value:    iconColor,
                            onChange: val => setAttributes( { iconColor: val } ),
                            help:     __( 'z.B. #ff0000 oder var(--color-primary)', 'media-lab-agency-core' ),
                        } ),
                        el( SelectControl, {
                            label:    __( 'Layout', 'media-lab-agency-core' ),
                            value:    layout,
                            options:  layoutOptions,
                            onChange: val => setAttributes( { layout: val } ),
                        } ),
                    ),
                ),
                el( 'div', blockProps,
                    el( 'div', {
                            className: 'ml-icon-text__icon',
                            style: iconColor ? { color: iconColor } : {},
                        },
                        icon,
                    ),
                    el( 'div', { className: 'ml-icon-text__body' },
                        el( RichText, {
                            tagName:     'h3',
                            className:   'ml-icon-text__title',
                            value:       title,
                            onChange:    val => setAttributes( { title: val } ),
                            placeholder: __( 'Titel…', 'media-lab-agency-core' ),
                        } ),
                        el( RichText, {
                            tagName:     'p',
                            className:   'ml-icon-text__text',
                            value:       text,
                            onChange:    val => setAttributes( { text: val } ),
                            placeholder: __( 'Beschreibung…', 'media-lab-agency-core' ),
                        } ),
                    ),
                ),
            );
        },

        save( { attributes } ) {
            const { icon, title, text, iconColor, layout } = attributes;
            const blockProps = useBlockProps.save( { className: `ml-block-icon-text ml-icon-text--${layout}` } );
            return el( 'div', blockProps,
                el( 'div', {
                        className:    'ml-icon-text__icon',
                        style:        iconColor ? { color: iconColor } : {},
                        'aria-hidden': 'true',
                    },
                    icon,
                ),
                el( 'div', { className: 'ml-icon-text__body' },
                    el( RichText.Content, { tagName: 'h3', className: 'ml-icon-text__title', value: title } ),
                    el( RichText.Content, { tagName: 'p',  className: 'ml-icon-text__text',  value: text } ),
                ),
            );
        },
    } );

    // =============================================================================
    // Parallax-Sektion
    //
    // Migration von ACF-PHP-Block (seit 1.16.0): ACF rendert Feldgruppen immer
    // in der Inspector-Sidebar, unabhängig von mode/position – daher hier als
    // native Block mit echten InnerBlocks im Canvas.
    // =============================================================================

    registerBlockType( 'medialab/parallax', {
        edit( { attributes, setAttributes } ) {
            const {
                imageId, imageUrl, imageAlt,
                speed, overlayColor, overlayOpacity, minHeight,
                contentAlign, contentWidth,
            } = attributes;

            const onSelectImage = ( media ) => {
                setAttributes( {
                    imageId:  media.id,
                    imageUrl: ( media.sizes && media.sizes.large && media.sizes.large.url ) || media.url,
                    imageAlt: media.alt || '',
                } );
            };

            const onRemoveImage = () => setAttributes( { imageId: 0, imageUrl: '', imageAlt: '' } );

            const blockProps = useBlockProps( {
                className: `ml-block-parallax ml-parallax--align-${contentAlign} ml-parallax--width-${contentWidth}`,
                style: { minHeight: minHeight + 'px', position: 'relative' },
            } );

            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: __( 'Hintergrundbild', 'media-lab-agency-core' ), initialOpen: true },
                        el( MediaUploadCheck, null,
                            el( MediaUpload, {
                                onSelect:     onSelectImage,
                                allowedTypes: [ 'image' ],
                                value:        imageId,
                                render( { open } ) {
                                    return el( 'div', null,
                                        imageUrl && el( 'img', {
                                            src: imageUrl,
                                            alt: imageAlt,
                                            style: {
                                                width: '100%',
                                                height: 'auto',
                                                borderRadius: '2px',
                                                marginBottom: '8px',
                                                cursor: 'pointer',
                                            },
                                            onClick: open,
                                        } ),
                                        el( Button, { onClick: open, variant: 'secondary', style: { marginBottom: '8px' } },
                                            imageUrl
                                                ? __( 'Bild ändern', 'media-lab-agency-core' )
                                                : __( 'Bild auswählen', 'media-lab-agency-core' )
                                        ),
                                        imageUrl && el( Button, { onClick: onRemoveImage, variant: 'link', isDestructive: true, style: { marginLeft: '8px' } },
                                            __( 'Entfernen', 'media-lab-agency-core' )
                                        ),
                                    );
                                },
                            } ),
                        ),
                    ),
                    el( PanelBody, { title: __( 'Effekt', 'media-lab-agency-core' ) },
                        el( RangeControl, {
                            label:    __( 'Parallax-Intensität', 'media-lab-agency-core' ),
                            value:    speed, min: 0, max: 100, step: 5,
                            onChange: val => setAttributes( { speed: val } ),
                        } ),
                        el( RangeControl, {
                            label:    __( 'Mindesthöhe (px)', 'media-lab-agency-core' ),
                            value:    minHeight, min: 100, max: 1200, step: 50,
                            onChange: val => setAttributes( { minHeight: val } ),
                        } ),
                    ),
                    el( PanelBody, { title: __( 'Overlay', 'media-lab-agency-core' ) },
                        el( PanelColorSettings, {
                            title:       __( 'Overlay-Farbe', 'media-lab-agency-core' ),
                            initialOpen: true,
                            colorSettings: [ {
                                value:    overlayColor,
                                onChange: val => setAttributes( { overlayColor: val || '#000000' } ),
                                label:    __( 'Farbe', 'media-lab-agency-core' ),
                            } ],
                        } ),
                        el( RangeControl, {
                            label:    __( 'Overlay-Deckkraft', 'media-lab-agency-core' ),
                            value:    overlayOpacity, min: 0, max: 100, step: 5,
                            onChange: val => setAttributes( { overlayOpacity: val } ),
                        } ),
                    ),
                    el( PanelBody, { title: __( 'Inhalt', 'media-lab-agency-core' ) },
                        el( SelectControl, {
                            label:    __( 'Ausrichtung', 'media-lab-agency-core' ),
                            value:    contentAlign,
                            options: [
                                { label: __( 'Oben', 'media-lab-agency-core' ),  value: 'top' },
                                { label: __( 'Mitte', 'media-lab-agency-core' ), value: 'center' },
                                { label: __( 'Unten', 'media-lab-agency-core' ), value: 'bottom' },
                            ],
                            onChange: val => setAttributes( { contentAlign: val } ),
                        } ),
                        el( SelectControl, {
                            label:    __( 'Breite', 'media-lab-agency-core' ),
                            value:    contentWidth,
                            options: [
                                { label: __( 'Eng (640px)', 'media-lab-agency-core' ),    value: 'narrow' },
                                { label: __( 'Mittel (960px)', 'media-lab-agency-core' ), value: 'medium' },
                                { label: __( 'Voll', 'media-lab-agency-core' ),           value: 'full' },
                            ],
                            onChange: val => setAttributes( { contentWidth: val } ),
                        } ),
                    ),
                ),
                el( 'section', blockProps,
                    el( 'div', {
                        className: 'ml-parallax__bg',
                        'aria-hidden': 'true',
                        style: imageUrl ? { backgroundImage: `url(${imageUrl})` } : { background: '#f0f0f0' },
                    } ),
                    ! imageUrl && el( 'p', { style: { textAlign: 'center', padding: '2rem', color: '#aaa', position: 'relative' } },
                        __( 'Parallax-Sektion – bitte Hintergrundbild oben im Inspector wählen.', 'media-lab-agency-core' )
                    ),
                    overlayOpacity > 0 && el( 'div', {
                        className: 'ml-parallax__overlay',
                        'aria-hidden': 'true',
                        style: { backgroundColor: overlayColor, opacity: overlayOpacity / 100 },
                    } ),
                    el( 'div', { className: 'ml-parallax__content' },
                        el( 'div', { className: 'ml-parallax__inner' },
                            el( InnerBlocks, {
                                template: [ [ 'core/paragraph', { placeholder: __( 'Inhalt über dem Bild…', 'media-lab-agency-core' ) } ] ],
                                templateLock: false,
                            } ),
                        ),
                    ),
                ),
            );
        },

        save( { attributes } ) {
            const {
                imageUrl, speed, overlayColor, overlayOpacity, minHeight,
                contentAlign, contentWidth,
            } = attributes;

            const dataAttrs = { 'data-parallax-speed': speed };
            if ( imageUrl ) dataAttrs[ 'data-parallax-img' ] = imageUrl;

            const blockProps = useBlockProps.save( Object.assign( {
                className: `ml-block-parallax ml-parallax--align-${contentAlign} ml-parallax--width-${contentWidth}`,
                style: { minHeight: minHeight + 'px' },
                'aria-label': __( 'Parallax-Sektion', 'media-lab-agency-core' ),
            }, dataAttrs ) );

            return el( 'section', blockProps,
                el( 'div', {
                    className: 'ml-parallax__bg',
                    'aria-hidden': 'true',
                    style: imageUrl ? { backgroundImage: `url(${imageUrl})` } : undefined,
                } ),
                overlayOpacity > 0 && el( 'div', {
                    className: 'ml-parallax__overlay',
                    'aria-hidden': 'true',
                    style: { backgroundColor: overlayColor, opacity: overlayOpacity / 100 },
                } ),
                el( 'div', { className: 'ml-parallax__content' },
                    el( 'div', { className: 'ml-parallax__inner' },
                        el( InnerBlocks.Content ),
                    ),
                ),
            );
        },
    } );

    // =============================================================================
    // Slider (Parent) + Folie (Child)
    //
    // Migration von ACF-Repeater (seit 1.16.0): Folien sind jetzt echte
    // InnerBlocks (medialab/slide) statt Repeater-Zeilen in der Sidebar –
    // Hinzufügen/Sortieren/Löschen direkt im Canvas über die normale
    // Block-Toolbar. Erzeugte Markup ist 1:1 identisch zur bisherigen
    // PHP-Ausgabe, sodass das bestehende Frontend-JS (block-slider.js) und
    // CSS unverändert weiterlaufen.
    // =============================================================================

    registerBlockType( 'medialab/slider', {
        edit( { attributes, setAttributes } ) {
            const {
                autoplay, autoplayDelay, loop, navigation, pagination,
                effect, speed, slidesPerView, spaceBetween, centered,
            } = attributes;

            const blockProps = useBlockProps( { className: 'ml-block-slider ml-block-slider--editor' } );

            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: __( 'Wiedergabe', 'media-lab-agency-core' ), initialOpen: true },
                        el( ToggleControl, {
                            label:    __( 'Autoplay', 'media-lab-agency-core' ),
                            checked:  autoplay,
                            onChange: val => setAttributes( { autoplay: val } ),
                        } ),
                        autoplay && el( RangeControl, {
                            label:    __( 'Autoplay-Delay (ms)', 'media-lab-agency-core' ),
                            value:    autoplayDelay, min: 500, max: 10000, step: 500,
                            onChange: val => setAttributes( { autoplayDelay: val } ),
                        } ),
                        el( ToggleControl, {
                            label:    __( 'Endlos-Loop', 'media-lab-agency-core' ),
                            checked:  loop,
                            onChange: val => setAttributes( { loop: val } ),
                        } ),
                        el( SelectControl, {
                            label:    __( 'Übergangseffekt', 'media-lab-agency-core' ),
                            value:    effect,
                            options: [
                                { label: __( 'Schieben', 'media-lab-agency-core' ),     value: 'slide' },
                                { label: __( 'Überblenden', 'media-lab-agency-core' ),  value: 'fade' },
                                { label: __( 'Coverflow', 'media-lab-agency-core' ),    value: 'coverflow' },
                            ],
                            onChange: val => setAttributes( { effect: val } ),
                        } ),
                        el( RangeControl, {
                            label:    __( 'Transition (ms)', 'media-lab-agency-core' ),
                            value:    speed, min: 100, max: 2000, step: 100,
                            onChange: val => setAttributes( { speed: val } ),
                        } ),
                    ),
                    el( PanelBody, { title: __( 'Layout', 'media-lab-agency-core' ) },
                        el( RangeControl, {
                            label:    __( 'Sichtbare Folien', 'media-lab-agency-core' ),
                            value:    slidesPerView, min: 1, max: 6, step: 1,
                            onChange: val => setAttributes( { slidesPerView: val } ),
                        } ),
                        el( RangeControl, {
                            label:    __( 'Abstand zwischen Folien (px)', 'media-lab-agency-core' ),
                            value:    spaceBetween, min: 0, max: 100, step: 4,
                            onChange: val => setAttributes( { spaceBetween: val } ),
                        } ),
                        el( ToggleControl, {
                            label:    __( 'Aktive Folie zentrieren', 'media-lab-agency-core' ),
                            checked:  centered,
                            onChange: val => setAttributes( { centered: val } ),
                        } ),
                    ),
                    el( PanelBody, { title: __( 'Navigation', 'media-lab-agency-core' ) },
                        el( ToggleControl, {
                            label:    __( 'Pfeile anzeigen', 'media-lab-agency-core' ),
                            checked:  navigation,
                            onChange: val => setAttributes( { navigation: val } ),
                        } ),
                        el( SelectControl, {
                            label:    __( 'Seitenzahlen', 'media-lab-agency-core' ),
                            value:    pagination,
                            options: [
                                { label: __( 'Punkte', 'media-lab-agency-core' ), value: 'bullets' },
                                { label: __( 'Leiste', 'media-lab-agency-core' ), value: 'progressbar' },
                                { label: __( 'Aus', 'media-lab-agency-core' ),    value: 'none' },
                            ],
                            onChange: val => setAttributes( { pagination: val } ),
                        } ),
                    ),
                ),
                el( 'div', blockProps,
                    el( 'p', { className: 'ml-slider__editor-hint', style: { color: '#6b7280', fontSize: '.8125rem', margin: '0 0 8px' } },
                        __( 'Folien über den Block-Inserter (+) unten hinzufügen, per Drag & Drop sortieren. Die Swiper-Vorschau (Pfeile/Autoplay) erscheint im Frontend.', 'media-lab-agency-core' )
                    ),
                    el( 'div', { className: 'ml-slider__wrapper' },
                        el( InnerBlocks, {
                            allowedBlocks: [ 'medialab/slide' ],
                            template:      [ [ 'medialab/slide' ] ],
                            templateLock:  false,
                            orientation:   'horizontal',
                        } ),
                    ),
                ),
            );
        },

        save( { attributes } ) {
            const {
                autoplay, autoplayDelay, loop, navigation, pagination,
                effect, speed, slidesPerView, spaceBetween, centered,
            } = attributes;

            const swiperConfig = {
                loop, speed, effect,
                slidesPerView, spaceBetween,
                centeredSlides: centered,
                grabCursor: true,
                a11y: { enabled: true },
            };
            if ( autoplay ) {
                swiperConfig.autoplay = { delay: autoplayDelay, disableOnInteraction: false, pauseOnMouseEnter: true };
            }
            if ( navigation ) swiperConfig.navigation = true;
            if ( pagination !== 'none' ) {
                swiperConfig.pagination = { clickable: true, type: pagination === 'progressbar' ? 'progressbar' : 'bullets' };
            }

            const classes = [
                'ml-block-slider',
                `ml-slider--effect-${effect}`,
                navigation ? 'ml-slider--has-nav' : '',
                pagination !== 'none' ? 'ml-slider--has-pagination' : '',
            ].filter( Boolean ).join( ' ' );

            const blockProps = useBlockProps.save( { className: classes } );

            return el( 'div', blockProps,
                el( 'div', {
                        className: 'swiper ml-slider__swiper',
                        'data-swiper': JSON.stringify( swiperConfig ),
                    },
                    el( 'div', { className: 'swiper-wrapper ml-slider__wrapper' },
                        el( InnerBlocks.Content ),
                    ),
                    navigation && el( 'button', {
                        className: 'swiper-button-prev ml-slider__btn',
                        'aria-label': __( 'Vorherige Folie', 'media-lab-agency-core' ),
                    } ),
                    navigation && el( 'button', {
                        className: 'swiper-button-next ml-slider__btn',
                        'aria-label': __( 'Nächste Folie', 'media-lab-agency-core' ),
                    } ),
                    pagination !== 'none' && el( 'div', { className: 'swiper-pagination ml-slider__pagination' } ),
                ),
            );
        },
    } );

    registerBlockType( 'medialab/slide', {
        edit( { attributes, setAttributes } ) {
            const { imageId, imageUrl, imageAlt, heading, text, buttonText, buttonUrl, buttonTarget } = attributes;

            const onSelectImage = ( media ) => {
                setAttributes( {
                    imageId:  media.id,
                    imageUrl: ( media.sizes && media.sizes.large && media.sizes.large.url ) || media.url,
                    imageAlt: media.alt || '',
                } );
            };
            const onRemoveImage = () => setAttributes( { imageId: 0, imageUrl: '', imageAlt: '' } );
            const blockProps = useBlockProps( { className: 'ml-slider__slide ml-slider__slide--editor' } );

            return el( 'div', blockProps,
                el( InspectorControls, null,
                    el( PanelBody, { title: __( 'Folie', 'media-lab-agency-core' ), initialOpen: true },
                        el( TextControl, {
                            label:    __( 'Button-URL', 'media-lab-agency-core' ),
                            value:    buttonUrl,
                            onChange: val => setAttributes( { buttonUrl: val } ),
                            placeholder: 'https://',
                        } ),
                        el( ToggleControl, {
                            label:    __( 'Link in neuem Tab öffnen', 'media-lab-agency-core' ),
                            checked:  buttonTarget,
                            onChange: val => setAttributes( { buttonTarget: val } ),
                        } ),
                        el( TextControl, {
                            label:    __( 'CSS-Klasse (optional)', 'media-lab-agency-core' ),
                            value:    attributes.customClass,
                            onChange: val => setAttributes( { customClass: val } ),
                        } ),
                    ),
                ),
                el( 'div', { className: 'ml-slider__slide-media' },
                    el( MediaUploadCheck, null,
                        el( MediaUpload, {
                            onSelect:     onSelectImage,
                            allowedTypes: [ 'image' ],
                            value:        imageId,
                            render( { open } ) {
                                return imageUrl
                                    ? el( 'div', { style: { position: 'relative' } },
                                        el( 'img', {
                                            src: imageUrl, alt: imageAlt,
                                            className: 'ml-slider__slide-img',
                                            style: { cursor: 'pointer' },
                                            onClick: open,
                                        } ),
                                        el( Button, { onClick: onRemoveImage, variant: 'link', isDestructive: true },
                                            __( 'Bild entfernen', 'media-lab-agency-core' )
                                        ),
                                    )
                                    : el( Button, { onClick: open, variant: 'secondary' },
                                        __( 'Bild auswählen', 'media-lab-agency-core' )
                                    );
                            },
                        } ),
                    ),
                ),
                el( 'div', { className: 'ml-slider__slide-content' },
                    el( RichText, {
                        tagName:     'h3',
                        className:   'ml-slider__slide-heading',
                        value:       heading,
                        onChange:    val => setAttributes( { heading: val } ),
                        placeholder: __( 'Überschrift…', 'media-lab-agency-core' ),
                    } ),
                    el( RichText, {
                        tagName:     'div',
                        className:   'ml-slider__slide-text',
                        value:       text,
                        onChange:    val => setAttributes( { text: val } ),
                        placeholder: __( 'Text…', 'media-lab-agency-core' ),
                    } ),
                    el( RichText, {
                        tagName:        'span',
                        className:      'btn ml-slider__slide-btn',
                        value:          buttonText,
                        onChange:       val => setAttributes( { buttonText: val } ),
                        placeholder:    __( 'Button-Text…', 'media-lab-agency-core' ),
                        allowedFormats: [],
                    } ),
                ),
            );
        },

        save( { attributes } ) {
            const { imageUrl, imageAlt, heading, text, buttonText, buttonUrl, buttonTarget, customClass } = attributes;
            const hasContent = heading || text || buttonText;
            const classes = [ 'swiper-slide', 'ml-slider__slide', customClass ].filter( Boolean ).join( ' ' );
            const blockProps = useBlockProps.save( { className: classes } );

            return el( 'div', blockProps,
                imageUrl && el( 'div', { className: 'ml-slider__slide-media' },
                    el( 'img', {
                        src: imageUrl, alt: imageAlt,
                        className: 'ml-slider__slide-img',
                        loading: 'lazy',
                        draggable: 'false',
                    } ),
                ),
                hasContent && el( 'div', { className: 'ml-slider__slide-content' },
                    heading && el( RichText.Content, { tagName: 'h3', className: 'ml-slider__slide-heading', value: heading } ),
                    text && el( RichText.Content, { tagName: 'div', className: 'ml-slider__slide-text', value: text } ),
                    buttonText && buttonUrl && el( 'a', {
                            href: buttonUrl,
                            className: 'btn ml-slider__slide-btn',
                            target: buttonTarget ? '_blank' : '_self',
                            rel: buttonTarget ? 'noopener noreferrer' : undefined,
                        },
                        el( RichText.Content, { value: buttonText } ),
                    ),
                ),
            );
        },
    } );

    // =============================================================================
    // Kachel-Raster + Kachel (seit 1.40.0)
    //
    // Eltern-Block (tile-grid): <ul role="list"> mit InnerBlocks, statisch.
    // Kind-Block (tile):        dynamisch (render.php), weil die Überschriften-
    //                           ebene per Block-Kontext vom Raster kommt.
    // =============================================================================

    const HEADING_LEVEL_OPTIONS = [
        { label: 'H2', value: 'h2' },
        { label: 'H3', value: 'h3' },
        { label: 'H4', value: 'h4' },
    ];

    const safeHeading = ( level ) => ( [ 'h2', 'h3', 'h4' ].indexOf( level ) !== -1 ? level : 'h3' );

    registerBlockType( 'medialab/tile-grid', {
        edit( { attributes, setAttributes } ) {
            const { columns, headingLevel } = attributes;
            const cols = Math.min( 4, Math.max( 1, columns || 3 ) );

            const blockProps = useBlockProps( {
                className: `ml-block-tile-grid ml-tile-grid--cols-${cols}`,
            } );
            const innerBlocksProps = useInnerBlocksProps( blockProps, {
                allowedBlocks:  [ 'medialab/tile' ],
                template:       [ [ 'medialab/tile' ], [ 'medialab/tile' ], [ 'medialab/tile' ] ],
                orientation:    'horizontal',
                renderAppender: InnerBlocks.ButtonBlockAppender,
            } );

            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: __( 'Raster', 'media-lab-agency-core' ), initialOpen: true },
                        el( RangeControl, {
                            label:    __( 'Spalten (Desktop)', 'media-lab-agency-core' ),
                            value:    cols,
                            min:      1,
                            max:      4,
                            onChange: val => setAttributes( { columns: val } ),
                            help:     __( 'Auf Tablets sind es höchstens 2, auf dem Smartphone 1 Spalte.', 'media-lab-agency-core' ),
                        } ),
                        el( SelectControl, {
                            label:    __( 'Überschriftenebene der Kacheln', 'media-lab-agency-core' ),
                            value:    safeHeading( headingLevel ),
                            options:  HEADING_LEVEL_OPTIONS,
                            onChange: val => setAttributes( { headingLevel: val } ),
                            help:     __( 'Unter einer H2-Abschnittsüberschrift H3 wählen, direkt unter der Seiten-H1 H2.', 'media-lab-agency-core' ),
                        } ),
                    ),
                ),
                el( 'ul', innerBlocksProps ),
            );
        },

        save( { attributes } ) {
            const cols = Math.min( 4, Math.max( 1, attributes.columns || 3 ) );
            const blockProps = useBlockProps.save( {
                className: `ml-block-tile-grid ml-tile-grid--cols-${cols}`,
                role:      'list', // Safari/VoiceOver: Listensemantik trotz list-style:none erhalten
            } );
            return el( 'ul', useInnerBlocksProps.save( blockProps ) );
        },
    } );

    // -------------------------------------------------------------------------
    // Icon-Auswahl für die Seitenleiste: Icon-Bibliothek (Tabler, Elegant Icons) mit Suche
    // und Kategorien sowie kuratierte Emoji; das Textfeld bleibt für eigene Zeichen.
    // -------------------------------------------------------------------------
    const ML_ICON_GROUPS = [
        { label: 'Werbemittel', icons: [
            [ '👕', 'T-Shirt' ], [ '👔', 'Hemd' ], [ '🧥', 'Jacke' ], [ '🧢', 'Kappe' ], [ '🧣', 'Schal' ],
            [ '🧤', 'Handschuhe' ], [ '👜', 'Tasche' ], [ '🎒', 'Rucksack' ], [ '🛍️', 'Einkaufstaschen' ],
            [ '🎁', 'Geschenk' ], [ '🖊️', 'Kugelschreiber' ], [ '✏️', 'Bleistift' ], [ '📓', 'Notizbuch' ],
            [ '☕', 'Tasse' ], [ '🥤', 'Trinkbecher' ], [ '🍶', 'Flasche' ], [ '🎈', 'Luftballon' ],
            [ '🎉', 'Feier' ], [ '🏆', 'Pokal' ], [ '🏅', 'Medaille' ], [ '☂️', 'Regenschirm' ],
            [ '🔑', 'Schlüssel' ], [ '🕶️', 'Sonnenbrille' ], [ '🏷️', 'Etikett' ],
        ] },
        { label: 'Druck & Design', icons: [
            [ '🖨️', 'Drucker' ], [ '🎨', 'Farbpalette' ], [ '🖌️', 'Pinsel' ], [ '✂️', 'Schere' ],
            [ '📐', 'Dreieck-Lineal' ], [ '📏', 'Lineal' ], [ '🖼️', 'Bild' ], [ '📄', 'Dokument' ],
            [ '📰', 'Zeitung' ], [ '📖', 'Buch' ], [ '📚', 'Bücher' ], [ '🔍', 'Lupe' ],
            [ '💡', 'Idee' ], [ '✨', 'Funken' ], [ '🌈', 'Regenbogen' ], [ '📸', 'Kamera' ],
            [ '🎬', 'Filmklappe' ], [ '🎥', 'Videokamera' ], [ '🖥️', 'Bildschirm' ], [ '📱', 'Smartphone' ],
        ] },
        { label: 'Service', icons: [
            [ '✅', 'Haken' ], [ '✔️', 'Häkchen' ], [ '⭐', 'Stern' ], [ '🚀', 'Rakete' ], [ '🎯', 'Ziel' ],
            [ '📋', 'Checkliste' ], [ '📝', 'Notiz' ], [ '🗓️', 'Kalender' ], [ '⏱️', 'Stoppuhr' ],
            [ '🚚', 'Lieferung' ], [ '📦', 'Paket' ], [ '🔄', 'Wiederholen' ], [ '🤝', 'Handschlag' ],
            [ '🛠️', 'Werkzeug' ], [ '⚙️', 'Zahnrad' ], [ '🔒', 'Schloss' ], [ '🌱', 'Pflanze' ],
            [ '♻️', 'Recycling' ], [ '👍', 'Daumen hoch' ], [ '💶', 'Euro' ], [ '🧮', 'Rechner' ],
            [ '🏭', 'Fabrik' ], [ '🌍', 'Weltkugel' ], [ '❤️', 'Herz' ],
        ] },
        { label: 'Kontakt', icons: [
            [ '📞', 'Telefon' ], [ '✉️', 'Brief' ], [ '📧', 'E-Mail' ], [ '💬', 'Sprechblase' ],
            [ '📍', 'Standort' ], [ '🕐', 'Uhr' ], [ '🌐', 'Globus' ], [ '👤', 'Person' ],
            [ '👥', 'Personen' ], [ '🎧', 'Headset' ], [ '❓', 'Fragezeichen' ], [ 'ℹ️', 'Information' ],
            [ '📣', 'Megafon' ], [ '🔔', 'Glocke' ],
        ] },
        { label: 'Zeichen', icons: [
            [ '→', 'Pfeil rechts' ], [ '←', 'Pfeil links' ], [ '↑', 'Pfeil hoch' ], [ '↓', 'Pfeil runter' ],
            [ '➜', 'Pfeil kräftig' ], [ '▶', 'Dreieck rechts' ], [ '✓', 'Haken' ], [ '✗', 'Kreuz' ],
            [ '★', 'Stern gefüllt' ], [ '☆', 'Stern leer' ], [ '●', 'Punkt' ], [ '■', 'Quadrat' ],
            [ '◆', 'Raute' ], [ '+', 'Plus' ], [ '%', 'Prozent' ], [ '€', 'Euro' ],
        ] },
    ];

    // Icon-Bibliothek (Sprites unter assets/icons/): index.json wird beim ersten Öffnen der Auswahl geladen.
    const mlIconCfg   = window.mlIconLibrary || {};
    const ML_ICON_ID  = /^(tabler|elegant):[a-z0-9_-]+$/;
    const mlIsIconId  = ( v ) => typeof v === 'string' && ML_ICON_ID.test( v );
    let   mlIconIndex = null;
    let   mlIconIndexPromise = null;

    function mlLoadIconIndex() {
        if ( mlIconIndex ) { return Promise.resolve( mlIconIndex ); }
        if ( ! mlIconCfg.baseUrl ) { return Promise.reject( new Error( 'no base url' ) ); }
        if ( ! mlIconIndexPromise ) {
            mlIconIndexPromise = fetch( mlIconCfg.baseUrl + 'index.json?ver=' + encodeURIComponent( mlIconCfg.version || '' ) )
                .then( ( r ) => { if ( ! r.ok ) { throw new Error( 'index ' + r.status ); } return r.json(); } )
                .then( ( data ) => {
                    data.icons = data.icons.map( ( i ) => ( {
                        id: i[ 0 ], label: i[ 1 ], cat: i[ 3 ],
                        hay: ( i[ 0 ] + ' ' + i[ 1 ] + ' ' + i[ 2 ] ).toLowerCase(),
                    } ) );
                    mlIconIndex = data;
                    return data;
                } )
                .catch( ( err ) => { mlIconIndexPromise = null; throw err; } );
        }
        return mlIconIndexPromise;
    }

    function mlIconHref( id ) {
        const [ set, name ] = id.split( ':' );
        return mlIconCfg.baseUrl + 'sprite-' + set + '.svg#' + set + '-' + name;
    }

    function mlIconGraphic( id, size ) {
        return el( 'svg', { width: size, height: size, 'aria-hidden': 'true', focusable: 'false', style: { display: 'block' } },
            el( 'use', { href: mlIconHref( id ) } )
        );
    }

    const ML_ICON_PAGE = 120;

    function MlIconPicker( { value, onChange } ) {
        const [ open, setOpen ]       = useState( false );
        const [ tab, setTab ]         = useState( 'icons' );
        const [ group, setGroup ]     = useState( 0 );
        const [ query, setQuery ]     = useState( '' );
        const [ cat, setCat ]         = useState( '' );
        const [ set, setSet ]         = useState( '' );
        const [ limit, setLimit ]     = useState( ML_ICON_PAGE );
        const [ index, setIndex ]     = useState( mlIconIndex );
        const [ failed, setFailed ]   = useState( false );

        useEffect( () => {
            if ( ! open || tab !== 'icons' || index ) { return; }
            let alive = true;
            mlLoadIconIndex().then( ( d ) => { if ( alive ) { setIndex( d ); } } ).catch( () => { if ( alive ) { setFailed( true ); } } );
            return () => { alive = false; };
        }, [ open, tab, index ] );

        const results = useMemo( () => {
            if ( ! index ) { return []; }
            const words = query.toLowerCase().split( /\s+/ ).filter( Boolean );
            return index.icons.filter( ( i ) =>
                ( ! cat || i.cat === cat ) &&
                ( ! set || i.id.indexOf( set + ':' ) === 0 ) &&
                words.every( ( w ) => i.hay.indexOf( w ) !== -1 )
            );
        }, [ index, query, cat, set ] );

        const pick = ( v ) => { onChange( v ); setOpen( false ); };
        const gridStyle = { display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(36px, 1fr))', gap: '4px' };
        const cellStyle = { justifyContent: 'center', height: '36px', minWidth: 0, padding: 0 };

        const iconsTab = () => {
            if ( failed || ! mlIconCfg.baseUrl ) {
                return el( 'p', null, __( 'Die Icon-Bibliothek konnte nicht geladen werden. Eigene Zeichen sind im Feld darunter weiterhin möglich.', 'media-lab-agency-core' ) );
            }
            if ( ! index ) {
                return el( 'p', null, __( 'Icons werden geladen …', 'media-lab-agency-core' ) );
            }
            const shown = results.slice( 0, limit );
            return el( Fragment, null,
                el( TextControl, {
                    label:       __( 'Suchen', 'media-lab-agency-core' ),
                    type:        'search',
                    value:       query,
                    placeholder: __( 'z. B. Shirt, Tasse, Telefon, Lieferung', 'media-lab-agency-core' ),
                    onChange:    ( v ) => { setQuery( v ); setLimit( ML_ICON_PAGE ); },
                } ),
                el( SelectControl, {
                    label:   __( 'Kategorie', 'media-lab-agency-core' ),
                    value:   cat,
                    options: [ { label: __( 'Alle Kategorien', 'media-lab-agency-core' ), value: '' } ]
                        .concat( index.categories.map( ( c ) => ( { label: c, value: c } ) ) ),
                    onChange: ( v ) => { setCat( v ); setLimit( ML_ICON_PAGE ); },
                } ),
                el( SelectControl, {
                    label:   __( 'Bibliothek', 'media-lab-agency-core' ),
                    value:   set,
                    options: [
                        { label: __( 'Alle', 'media-lab-agency-core' ), value: '' },
                        { label: index.sets.tabler.label,  value: 'tabler' },
                        { label: index.sets.elegant.label, value: 'elegant' },
                    ],
                    onChange: ( v ) => { setSet( v ); setLimit( ML_ICON_PAGE ); },
                } ),
                el( 'p', { style: { margin: '0 0 8px' }, 'aria-live': 'polite' },
                    results.length + ' ' + __( 'Icons', 'media-lab-agency-core' ) ),
                el( 'div', { style: gridStyle },
                    shown.map( ( i ) => el( Button, {
                        key:            i.id,
                        label:          i.label + ( i.id.indexOf( 'elegant:' ) === 0 ? ' (Elegant)' : '' ),
                        showTooltip:    true,
                        variant:        i.id === value ? 'primary' : 'secondary',
                        'aria-pressed': i.id === value,
                        onClick:        () => pick( i.id ),
                        style:          cellStyle,
                    }, mlIconGraphic( i.id, 22 ) ) )
                ),
                results.length > limit && el( Button, {
                    variant: 'tertiary',
                    style:   { marginTop: '8px' },
                    onClick: () => setLimit( limit + ML_ICON_PAGE ),
                }, __( 'Mehr anzeigen', 'media-lab-agency-core' ) )
            );
        };

        const emojiTab = () => {
            const current = ML_ICON_GROUPS[ group ];
            return el( Fragment, null,
                el( 'div', { style: { display: 'flex', gap: '4px', flexWrap: 'wrap', marginBottom: '8px' } },
                    ML_ICON_GROUPS.map( ( g, i ) => el( Button, {
                        key:            g.label,
                        variant:        i === group ? 'primary' : 'tertiary',
                        size:           'small',
                        onClick:        () => setGroup( i ),
                        'aria-pressed': i === group,
                    }, g.label ) )
                ),
                el( 'div', { style: gridStyle },
                    current.icons.map( ( [ glyph, name ] ) => el( Button, {
                        key:            glyph,
                        label:          name,
                        showTooltip:    true,
                        variant:        glyph === value ? 'primary' : 'secondary',
                        'aria-pressed': glyph === value,
                        onClick:        () => pick( glyph ),
                        style:          Object.assign( { fontSize: '20px' }, cellStyle ),
                    }, glyph ) )
                )
            );
        };

        return el( 'div', { className: 'ml-icon-picker', style: { marginBottom: '16px' } },
            el( 'div', { style: { display: 'flex', gap: '12px', alignItems: 'center', flexWrap: 'wrap' } },
                value && el( 'span', { style: { display: 'inline-flex', width: '36px', height: '36px', alignItems: 'center', justifyContent: 'center', border: '1px solid #ddd', borderRadius: '4px', fontSize: '20px' } },
                    mlIsIconId( value ) ? mlIconGraphic( value, 24 ) : value
                ),
                el( Button, {
                    variant:         'secondary',
                    onClick:         () => setOpen( ! open ),
                    'aria-expanded': open,
                }, open ? __( 'Auswahl schließen', 'media-lab-agency-core' ) : __( 'Icon auswählen', 'media-lab-agency-core' ) ),
                value && el( Button, {
                    variant:       'link',
                    isDestructive: true,
                    onClick:       () => onChange( '' ),
                }, __( 'Icon entfernen', 'media-lab-agency-core' ) )
            ),
            open && el( 'div', { style: { marginTop: '12px' } },
                el( 'div', { style: { display: 'flex', gap: '4px', marginBottom: '12px' } },
                    el( Button, { variant: tab === 'icons' ? 'primary' : 'tertiary', size: 'small', 'aria-pressed': tab === 'icons', onClick: () => setTab( 'icons' ) }, __( 'Icons', 'media-lab-agency-core' ) ),
                    el( Button, { variant: tab === 'emoji' ? 'primary' : 'tertiary', size: 'small', 'aria-pressed': tab === 'emoji', onClick: () => setTab( 'emoji' ) }, __( 'Emoji', 'media-lab-agency-core' ) )
                ),
                tab === 'icons' ? iconsTab() : emojiTab()
            )
        );
    }

    registerBlockType( 'medialab/tile', {
        edit( { attributes, setAttributes, context } ) {
            const { icon, imageId, imageUrl, imageAlt, title, text, linkUrl, linkText, linkTarget } = attributes;
            const level = safeHeading( context && context[ 'medialab/headingLevel' ] );

            const onSelectImage = ( media ) => {
                setAttributes( {
                    imageId:  media.id,
                    imageUrl: ( media.sizes && media.sizes.large && media.sizes.large.url ) || media.url,
                    imageAlt: media.alt || '',
                } );
            };
            const onRemoveImage = () => setAttributes( { imageId: 0, imageUrl: '', imageAlt: '' } );

            const blockProps = useBlockProps( { className: 'ml-tile ml-tile--editor' } );

            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: __( 'Icon oder Bild', 'media-lab-agency-core' ), initialOpen: true },
                        el( MlIconPicker, {
                            value:    icon,
                            onChange: val => setAttributes( { icon: val } ),
                        } ),
                        el( TextControl, {
                            label:    __( 'Eigenes Icon (Emoji oder Zeichen)', 'media-lab-agency-core' ),
                            value:    mlIsIconId( icon ) ? '' : icon,
                            onChange: val => setAttributes( { icon: val } ),
                            help:     __( 'Alternativ ein eigenes Emoji oder Zeichen einfügen (Mac Ctrl+Cmd+Leertaste, Windows Win+Punkt). Rein dekorativ, wird Screenreadern nicht vorgelesen.', 'media-lab-agency-core' ),
                        } ),
                        el( MediaUploadCheck, null,
                            el( MediaUpload, {
                                onSelect:     onSelectImage,
                                allowedTypes: [ 'image' ],
                                value:        imageId,
                                render( { open } ) {
                                    return el( Fragment, null,
                                        imageUrl && el( 'img', {
                                            src:   imageUrl,
                                            alt:   '',
                                            style: { maxWidth: '100%', height: 'auto', marginBottom: '8px' },
                                        } ),
                                        el( Button, { onClick: open, variant: 'secondary' },
                                            imageUrl
                                                ? __( 'Bild ersetzen', 'media-lab-agency-core' )
                                                : __( 'Bild auswählen', 'media-lab-agency-core' )
                                        ),
                                        imageUrl && el( Button, { onClick: onRemoveImage, variant: 'link', isDestructive: true, style: { marginLeft: '12px' } },
                                            __( 'Bild entfernen', 'media-lab-agency-core' )
                                        ),
                                    );
                                },
                            } ),
                        ),
                        imageUrl && el( TextControl, {
                            label:    __( 'Alternativtext', 'media-lab-agency-core' ),
                            value:    imageAlt,
                            onChange: val => setAttributes( { imageAlt: val } ),
                            help:     __( 'Leer lassen, wenn das Bild nur Dekoration ist.', 'media-lab-agency-core' ),
                        } ),
                    ),
                    el( PanelBody, { title: __( 'Link', 'media-lab-agency-core' ), initialOpen: true },
                        el( TextControl, {
                            label:       __( 'Link-URL', 'media-lab-agency-core' ),
                            value:       linkUrl,
                            onChange:    val => setAttributes( { linkUrl: val } ),
                            placeholder: 'https://',
                            help:        __( 'Mit Button-Text wird ein Button angezeigt, ohne Button-Text ist der Titel der Link. In beiden Fällen ist die ganze Kachel klickbar.', 'media-lab-agency-core' ),
                        } ),
                        el( ToggleControl, {
                            label:    __( 'In neuem Tab öffnen', 'media-lab-agency-core' ),
                            checked:  linkTarget,
                            onChange: val => setAttributes( { linkTarget: val } ),
                        } ),
                    ),
                ),
                el( 'li', blockProps,
                    imageUrl && el( 'div', { className: 'ml-tile__media' },
                        el( 'img', { src: imageUrl, alt: imageAlt, className: 'ml-tile__img' } )
                    ),
                    icon && el( 'div', { className: 'ml-tile__icon', 'aria-hidden': 'true' }, icon ),
                    el( 'div', { className: 'ml-tile__body' },
                        el( RichText, {
                            tagName:        level,
                            className:      'ml-tile__title',
                            value:          title,
                            onChange:       val => setAttributes( { title: val } ),
                            placeholder:    __( 'Titel…', 'media-lab-agency-core' ),
                            allowedFormats: [ 'core/bold', 'core/italic' ],
                        } ),
                        el( RichText, {
                            tagName:        'p',
                            className:      'ml-tile__text',
                            value:          text,
                            onChange:       val => setAttributes( { text: val } ),
                            placeholder:    __( 'Text…', 'media-lab-agency-core' ),
                            allowedFormats: [ 'core/bold', 'core/italic' ],
                        } ),
                        el( 'p', { className: 'ml-tile__action' },
                            el( RichText, {
                                tagName:        'span',
                                className:      'btn btn--outline ml-tile__link',
                                value:          linkText,
                                onChange:       val => setAttributes( { linkText: val } ),
                                placeholder:    __( 'Button-Text (optional)…', 'media-lab-agency-core' ),
                                allowedFormats: [],
                            } ),
                        ),
                    ),
                ),
            );
        },

        // Dynamisch: Ausgabe über blocks/tile/render.php
        save() {
            return null;
        },
    } );

    // =============================================================================
    // Schritte + Schritt (seit 1.40.0)
    //
    // Eltern-Block (steps): <ol role="list"> mit InnerBlocks, statisch.
    // Kind-Block (step):    dynamisch (render.php). Die Nummer erzeugt CSS aus
    //                       der Reihenfolge (counter), sie wird nicht gespeichert.
    // =============================================================================

    registerBlockType( 'medialab/steps', {
        edit( { attributes, setAttributes } ) {
            const { orientation, headingLevel } = attributes;
            const orient = orientation === 'vertical' ? 'vertical' : 'horizontal';

            const blockProps = useBlockProps( {
                className: `ml-block-steps ml-steps--${orient}`,
            } );
            const innerBlocksProps = useInnerBlocksProps( blockProps, {
                allowedBlocks:  [ 'medialab/step' ],
                template:       [ [ 'medialab/step' ], [ 'medialab/step' ], [ 'medialab/step' ] ],
                orientation:    orient,
                renderAppender: InnerBlocks.ButtonBlockAppender,
            } );

            return el( Fragment, null,
                el( InspectorControls, null,
                    el( PanelBody, { title: __( 'Schritte', 'media-lab-agency-core' ), initialOpen: true },
                        el( SelectControl, {
                            label:    __( 'Anordnung (Desktop)', 'media-lab-agency-core' ),
                            value:    orient,
                            options:  [
                                { label: __( 'Nebeneinander', 'media-lab-agency-core' ), value: 'horizontal' },
                                { label: __( 'Untereinander', 'media-lab-agency-core' ), value: 'vertical' },
                            ],
                            onChange: val => setAttributes( { orientation: val } ),
                            help:     __( 'Auf dem Smartphone stehen die Schritte immer untereinander.', 'media-lab-agency-core' ),
                        } ),
                        el( SelectControl, {
                            label:    __( 'Überschriftenebene der Schritte', 'media-lab-agency-core' ),
                            value:    safeHeading( headingLevel ),
                            options:  HEADING_LEVEL_OPTIONS,
                            onChange: val => setAttributes( { headingLevel: val } ),
                        } ),
                    ),
                ),
                el( 'ol', innerBlocksProps ),
            );
        },

        save( { attributes } ) {
            const orient = attributes.orientation === 'vertical' ? 'vertical' : 'horizontal';
            const blockProps = useBlockProps.save( {
                className: `ml-block-steps ml-steps--${orient}`,
                role:      'list',
            } );
            return el( 'ol', useInnerBlocksProps.save( blockProps ) );
        },
    } );

    registerBlockType( 'medialab/step', {
        edit( { attributes, setAttributes, context } ) {
            const { title, text } = attributes;
            const level = safeHeading( context && context[ 'medialab/headingLevel' ] );

            const blockProps = useBlockProps( { className: 'ml-step ml-step--editor' } );

            return el( 'li', blockProps,
                el( 'div', { className: 'ml-step__body' },
                    el( RichText, {
                        tagName:        level,
                        className:      'ml-step__title',
                        value:          title,
                        onChange:       val => setAttributes( { title: val } ),
                        placeholder:    __( 'Titel des Schritts…', 'media-lab-agency-core' ),
                        allowedFormats: [ 'core/bold', 'core/italic' ],
                    } ),
                    el( RichText, {
                        tagName:        'p',
                        className:      'ml-step__text',
                        value:          text,
                        onChange:       val => setAttributes( { text: val } ),
                        placeholder:    __( 'Text…', 'media-lab-agency-core' ),
                        allowedFormats: [ 'core/bold', 'core/italic', 'core/link' ],
                    } ),
                ),
            );
        },

        // Dynamisch: Ausgabe über blocks/step/render.php
        save() {
            return null;
        },
    } );

} ); // end wp.domReady
