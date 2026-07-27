<?php
function micinterart_register_gedicht_pattern() {
    register_block_pattern(
        'micinterart/gedicht',
        [
            'title'       => __( 'Gedicht', 'micinterart' ),
            'description' => _x( 'Formatvorlage für Gedichte mit Titel, Strophen und sauberer Typografie.', 'Block pattern description', 'micinterart' ),
            'content'     => '
                <!-- wp:group {"className":"gedicht"} -->
                <div class="wp-block-group gedicht">
                    <!-- wp:heading {"textAlign":"center","level":1,"className":"gedicht-titel"} -->
                    <h1 class="has-text-align-center gedicht-titel">Titel des Gedichts</h1>
                    <!-- /wp:heading -->

                    <!-- wp:group {"className":"gedicht-text"} -->
                    <div class="wp-block-group gedicht-text">
                        <!-- wp:paragraph {"align":"center"} -->
                        <p class="has-text-align-center">Erste Zeile<br>Zweite Zeile<br>Dritte Zeile</p>
                        <!-- /wp:paragraph -->

                        <!-- wp:paragraph {"align":"center"} -->
                        <p class="has-text-align-center">Neue Strophe<br>Zweite Zeile<br>Dritte Zeile</p>
                        <!-- /wp:paragraph -->
                    </div>
                    <!-- /wp:group -->
                </div>
                <!-- /wp:group -->
            ',
        ]
    );
}
add_action( 'init', 'micinterart_register_gedicht_pattern' );