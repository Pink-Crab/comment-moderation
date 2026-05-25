/**
 * Hello_World shortcode behaviour.
 *
 * Built by wp-scripts (entry registered in package.json's build:scripts).
 * Output lands at assets/build/scripts/hello-world.js and is enqueued by
 * src/Presentation/Hook/Hello_World.php::enqueue_assets().
 *
 * Trivial example: marks every shortcode container with `.is-mounted` once
 * the DOM is ready, so the SCSS rule kicks in. Replace with whatever you
 * actually need.
 */

const SELECTOR = '.pinkcrab-comment-moderation-hello-world';

const mount = () => {
	document.querySelectorAll( SELECTOR ).forEach( ( el ) => {
		el.classList.add( 'is-mounted' );
	} );
};

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', mount );
} else {
	mount();
}
