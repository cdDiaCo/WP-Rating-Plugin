<?php
/**
 * Default template for movie reviews
 */


get_header(); ?>

<div id="primary" class="content-area">
    <main id="main" class="content site-main" role="main">
        <?php
        // Start the loop.
        while ( have_posts() ) : the_post();
        ?>

        <article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
        <?php
        // Post thumbnail.
        twentyfifteen_post_thumbnail();
        ?>

        <header class="entry-header">
            <?php
            if ( is_single() ) :
                the_title( '<h1 class="new_entry-title">', '</h1>' );
            else :
                the_title( sprintf( '<h2 class="new_entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' );
            endif;
            ?>
            <!--
            <div class="ratingDiv">
                <div class="ratingButtons">
                    <span class="dashicons dashicons-thumbs-up"> </span>
                    <span class="dashicons dashicons-thumbs-down"> </span>
                </div>
                <div class="totalScore">
                    <span class="totalScoreValue"></span>
                    <span class="totalScoreText"></span>
                </div>
            </div>
            -->
        </header><!-- .entry-header -->



        <div class="entry-content">
            <?php
            /* translators: %s: Name of current post */
            the_content( sprintf(
                __( 'Continue reading %s', 'twentyfifteen' ),
                the_title( '<span class="screen-reader-text">', '</span>', false )
            ) );

            the_meta();

            wp_link_pages( array(
                'before'      => '<div class="page-links"><span class="page-links-title">' . __( 'Pages:', 'twentyfifteen' ) . '</span>',
                'after'       => '</div>',
                'link_before' => '<span>',
                'link_after'  => '</span>',
                'pagelink'    => '<span class="screen-reader-text">' . __( 'Page', 'twentyfifteen' ) . ' </span>%',
                'separator'   => '<span class="screen-reader-text">, </span>',
            ) );
            ?>
        </div><!-- .entry-content -->

        <?php
        // Author bio.
        if ( is_single() && get_the_author_meta( 'description' ) ) :
            get_template_part( 'author-bio' );
        endif;
        ?>

        <footer class="entry-footer">
            <?php twentyfifteen_entry_meta(); ?>
            <?php edit_post_link( __( 'Edit', 'twentyfifteen' ), '<span class="edit-link">', '</span>' ); ?>
        </footer><!-- .entry-footer -->

        </article><!-- #post-## -->
        <?php
        endwhile;
        ?>

    </main><!-- .site-main -->
</div><!-- .content-area -->

<?php get_footer(); ?>