<?php
/**
 * The Template for displaying all single posts.
 * @author Fox
 * @package cshero
 */
get_header(); ?>
<?php global $smof_data,$breadcrumb; $layout = cshero_generetor_layout(); ?>
	<section id="primary" class="single-post-wrap blog-modern-wrap content-area<?php if($breadcrumb == '0'){ echo ' no_breadcrumb'; }; ?><?php echo esc_attr($layout->class); ?>">
        <div class="container">
            <div class="row">
            	<?php if($layout->left1_col):?>
            		<div class="left-wrap <?php echo esc_attr($layout->left1_col); ?>">
            		     <div id="secondary" class="widget-area" role="complementary">
							<div id="primary-sidebar" class="primary-sidebar widget-area" role="complementary">
								<?php dynamic_sidebar($layout->left1_sidebar); ?>
							</div>
						 </div>
            		</div>
            	<?php endif; ?>
                <div class="content-wrap <?php echo esc_attr($layout->blog); ?>">
                    <main id="main" class="site-main" role="main">
                        <?php while ( have_posts() ) : the_post(); ?>
                            <?php get_template_part( 'framework/templates/single/single',get_post_format()); ?>
                            <div class="row post-details-footer">
                                <div class="cs-blog-about-author col-xs-12 col-sm-12 col-md-6 col-lg-6">
                                    <div class="author-avatar vcard">
                                        <?php echo get_avatar(get_the_author_meta('ID'), 140); ?>
                                    </div>
                                    <div class="author-info">
                                        <h5><?php echo get_the_author(); ?></h5>
                                        <?php echo get_the_author_meta('description'); ?>
                                    </div>
                                </div>
                                <?php if($smof_data['show_social_post']):?>
                                    <div class="cs-blog-share col-xs-12 col-sm-12 col-md-6 col-lg-6">
                                        <?php
                                            $attachment_image = wp_get_attachment_image_src(get_post_thumbnail_id($post->ID), 'full', false);
                                            $img = esc_attr($attachment_image[0]);
                                            $title = get_the_title();
                                            echo cs_socials_share(get_the_permalink(),$img, $title,get_comments_link($post->ID));
                                        ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php
                                if($smof_data['show_navigation_post'] == '1'){
                                    cshero_post_nav();
                                }
                            ?>
                             
                            <?php  
                                get_template_part('framework/templates/single/related');
                            ?>  
                            
                            <?php
                                $smof_data['rating_categories'] = isset($smof_data['rating_categories'])?$smof_data['rating_categories']:array();
                                if($smof_data['show_rating'] and count(array_intersect($smof_data['rating_categories'],wp_get_post_categories(get_the_ID())))>0):
                            ?>
                            <div class="cshero-rate">
                                <h3><?php echo __('Rate for this post','wp_suarez');?></h3>
                                <?php if(function_exists('the_ratings')) { the_ratings(); } ?>
                            </div>
                            <?php endif;?>
                            <?php
                            // If comments are open or we have at least one comment, load up the comment template
                            if (cshero_show_comments('post') == '1') :
                                comments_template();
                            endif;
                            ?>
                        <?php endwhile; // end of the loop. ?>
                    </main><!-- #main -->
                </div>
                <?php if(!empty($layout->right1_col)):?>
            		<div class="right-wrap <?php echo esc_attr($layout->right1_col); ?>">
            		     <div id="secondary" class="widget-area" role="complementary">
							<div id="primary-sidebar" class="primary-sidebar widget-area" role="complementary">
								<?php dynamic_sidebar($layout->right1_sidebar); ?>
							</div>
						 </div>
            		</div>
            	<?php endif; ?>
            </div>
        </div>
	</section><!-- #primary -->
<?php get_footer(); ?>