/**
 * Gutenberg Block für Micinterart Gallery
 * 
 * @package Micinterart Gallery
 * @version 2.0.0
 */

(function(wp) {
    'use strict';
    
    const { registerBlockType } = wp.blocks;
    const { TextControl, SelectControl, PanelBody } = wp.components;
    const { InspectorControls, useBlockProps } = wp.blockEditor;
    const { ServerSideRender } = wp.serverSideRender || wp.components;
    const { createElement: el } = wp.element;
    
    /**
     * Registriere Micinterart Gallery Block
     */
    registerBlockType('micinterart/gallery', {
        title: 'Micinterart Galerie',
        description: 'Zeigt eine Galerie von Kunstwerken mit Lightbox an',
        icon: 'format-gallery',
        category: 'widgets',
        attributes: {
            ids: {
                type: 'string',
                default: ''
            },
            serie: {
                type: 'string',
                default: ''
            }
        },
        
        edit: function(props) {
            const { attributes, setAttributes } = props;
            const blockProps = useBlockProps();
            
            // Serie-Optionen laden (wenn verfügbar)
            const serieOptions = [
                { label: 'Alle Serien', value: '' }
            ];
            
            // Hier könnten Serien dynamisch geladen werden
            // Für jetzt nur Platzhalter
            
            return el(
                'div',
                blockProps,
                [
                    // Sidebar Controls
                    el(
                        InspectorControls,
                        {},
                        el(
                            PanelBody,
                            {
                                title: 'Galerie-Einstellungen',
                                initialOpen: true
                            },
                            [
                                el(TextControl, {
                                    label: 'Werk-IDs',
                                    help: 'Kommagetrennte Liste von Werk-IDs (z.B. 1,5,12). Leer lassen für alle Werke.',
                                    value: attributes.ids,
                                    onChange: function(value) {
                                        setAttributes({ ids: value });
                                    }
                                }),
                                el(TextControl, {
                                    label: 'Serie (Slug)',
                                    help: 'Zeige nur Werke einer bestimmten Serie (z.B. "abstrakt")',
                                    value: attributes.serie,
                                    onChange: function(value) {
                                        setAttributes({ serie: value });
                                    }
                                })
                            ]
                        )
                    ),
                    
                    // Block Preview
                    el(
                        'div',
                        { className: 'micinterart-gallery-block-preview' },
                        [
                            el('div', {
                                style: {
                                    padding: '40px',
                                    background: '#f5f5f5',
                                    border: '2px dashed #ccc',
                                    borderRadius: '8px',
                                    textAlign: 'center'
                                }
                            }, [
                                el('span', {
                                    className: 'dashicons dashicons-format-gallery',
                                    style: {
                                        fontSize: '60px',
                                        color: '#999',
                                        display: 'block',
                                        marginBottom: '15px'
                                    }
                                }),
                                el('p', {
                                    style: {
                                        margin: 0,
                                        color: '#666',
                                        fontSize: '16px',
                                        fontWeight: '500'
                                    }
                                }, 'Micinterart Galerie'),
                                el('p', {
                                    style: {
                                        margin: '10px 0 0',
                                        color: '#999',
                                        fontSize: '14px'
                                    }
                                }, attributes.ids 
                                    ? `Zeige Werke: ${attributes.ids}` 
                                    : attributes.serie 
                                        ? `Zeige Serie: ${attributes.serie}`
                                        : 'Zeige alle Werke'
                                )
                            ])
                        ]
                    )
                ]
            );
        },
        
        save: function() {
            // Server-side rendering, kein Frontend-Save nötig
            return null;
        }
    });
    
})(window.wp);