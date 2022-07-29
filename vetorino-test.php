<?php
/**
 * Plugin Name: Vetorino Test
 * Plugin URI: http://sygrie.fr
 * Description: plugin woocommerce pour cumuler des points à chaque commande et crée des coupons de réduction en echange de points
 * Version: 1.0
 * Author: Enzo Geronzi
 * Text Domain: vt
 */
require_once (__DIR__.'/lib/autoload.php');

function vt_enqueue(): void {
	wp_enqueue_style( 'style-vt', plugin_dir_url( __FILE__ ) . 'css/style.css');
	wp_enqueue_script( 'script-vt', plugin_dir_url( __FILE__ ) . 'js/script.js', array('jquery'), '1.0.0', true );

    // permet de paramétrer l'url du formulaire via l'objet vt_form_object
	wp_localize_script( 'script-vt', 'vt_form_object', array('ajax_url' => admin_url('admin-ajax.php')) );
}
add_action( 'wp_enqueue_scripts', 'vt_enqueue' );

// actions pour le formulaire ajax "loyalty_points_form"
add_action( 'wp_ajax_loyalty_points_form', 'loyalty_points_form' );
add_action( 'wp_ajax_nopriv_loyalty_points_form', 'loyalty_points_form');

// Ajout une option pour définir le minimum de loyalty_points qu'il faut avoir pour crée un coupon
if (!get_option('vt_minimum_loyalty_points')) {
	add_option('vt_minimum_loyalty_points', 50);
}



// Action pour ajouter les loyalty_points
function vt_payment_complete( $order_id ): void {
	$order = wc_get_order( $order_id );
	$user_id = $order->get_user_id();
	if( $order->get_user() ){
		if (!metadata_exists('user', $user_id, 'loyalty_points')) {
			add_user_meta($user_id, 'loyalty_points', 0);
		}
		$current_loyalty_points = get_user_meta($user_id, 'loyalty_points', true);
		$point = floor((5 / 100) * $order->get_total());
		update_user_meta($user_id, 'loyalty_points', $current_loyalty_points + $point);
	}
}
add_action( 'woocommerce_order_status_completed', 'vt_payment_complete' );
// Dans le cas de l'utilisation du plugin "WooCommerce Payments"
// add_action( 'woocommerce_payment_complete', 'vt_payment_complete' );

// ensemble de fonction pour ajouter la tab "loyalty_points" au panel user
function vt_register_loyalty_points_endpoint(): void {
	add_rewrite_endpoint('loyalty_points', EP_ROOT | EP_PAGES);
}
add_action( 'init', 'vt_register_loyalty_points_endpoint');

function vt_loyalty_points_query_vars($vars) {
    $vars[] = 'loyalty_points';
    return $vars;
}
add_filter( 'query_vars', 'vt_loyalty_points_query_vars');

function vt_add_loyalty_points_tab( $items ) {
	$items['loyalty_points'] = __('Points de fidélité', 'vt');
	return $items;
}
add_filter( 'woocommerce_account_menu_items', 'vt_add_loyalty_points_tab' );

function vt_add_loyalty_points_content(): void {
	global $current_user;
	if (!metadata_exists('user', $current_user->ID, 'loyalty_points')) {
		add_user_meta($current_user->ID, 'loyalty_points', 0);
	}
	$current_loyalty_points = get_user_meta($current_user->ID, 'loyalty_points', true);
    echo '<div id="loyalty_points_content">';
	echo '<p>'.sprintf(__('Vous avez un totale de %s points de fidélité.', 'vt'), '<strong>'.$current_loyalty_points.'</strong>').'</p>';

	?><p class="vt_msg nopoints_msg"<?php if ($current_loyalty_points >= get_option('vt_minimum_loyalty_points')) {echo ' style="display: none"';} ?>><?php echo sprintf(__('Il vous faut aux moins %s points pour pouvoir générer des coupons de reduction.'), get_option('vt_minimum_loyalty_points')); ?></p><?php

	if ($current_loyalty_points >= get_option('vt_minimum_loyalty_points')) {
		?><form id="loyalty_points_form" action="" method="post" data-min-points="<?php echo get_option('vt_minimum_loyalty_points') ?>">
            <div class="row align-items-center">
            <div class="col">
                <input type="range" class="form-range points_to_discount" name="points_to_discount" id="points_to_discount_range" min="1" max="<?php echo $current_loyalty_points; ?>" value="10">
            </div>
            <div class="col">
                <input class="points_to_discount" id="points_to_discount_num" type="number" min="1" max="<?php echo $current_loyalty_points; ?>" value="10">
            </div>
            </div>
            <button class="btn btn-primary" type="submit" value="submit"><?php _e('Soumettre', 'vt'); ?></button>
        </form>
        <p class="vt_msg success_msg" style="display: none">Votre coupon a bien était généré.</p>
        <p class="vt_msg error_msg" style="display: none">Une erreur c'est produite</p><?php
	}
	echo '</div>';
}
add_action( 'woocommerce_account_loyalty_points_endpoint', 'vt_add_loyalty_points_content' );


// fonction pour la validation du formulaire ajax "loyalty_points_form"
function loyalty_points_form(): void {
	global $current_user;

    if (isset($_POST['points_to_discount'])) {
	    if (!metadata_exists('user', $current_user->ID, 'loyalty_points')) {
		    add_user_meta($current_user->ID, 'loyalty_points', 0);
	    }
	    $current_loyalty_points = get_user_meta($current_user->ID, 'loyalty_points', true);
	    if ($current_loyalty_points >= get_option('vt_minimum_loyalty_points') && $_POST['points_to_discount'] <= $current_loyalty_points) {
		    $coupon = new WC_Coupon();

            // generation de code
		    $char = "ABCDEFGHJKMNPQRSTUVWXYZ23456789";
		    $char_length = 32;
		    $random_string = substr(str_shuffle($char), 0, $char_length);

            $coupon->set_code($random_string);
            $coupon->set_description(sprintf(__('Créer pour %s', 'vt'), $current_user->user_email));
		    $coupon->set_amount( $_POST['points_to_discount'] );
		    $coupon->set_individual_use( true );
		    $coupon->set_usage_limit( 1 );
		    $coupon->save();

		    // API key authorization
		    $config = SendinBlue\Client\Configuration::getDefaultConfiguration()->setApiKey('api-key', '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');

		    $apiInstance = new SendinBlue\Client\Api\TransactionalEmailsApi(
                // If you want use custom http client, pass your client which implements `GuzzleHttp\ClientInterface`.
                // This is optional, `GuzzleHttp\Client` will be used as default.
			    new GuzzleHttp\Client(),
			    $config
		    );
		    $sendSmtpEmail = new \SendinBlue\Client\Model\SendSmtpEmail();
		    $sendSmtpEmail['to'] = array(array('email'=>$current_user->user_email, 'name'=>$current_user->display_name));
		    $sendSmtpEmail['templateId'] = 2;
		    $sendSmtpEmail['params'] = array('n_points'=> intval($_POST['points_to_discount']), 'code'=>$random_string , 'name'=>$current_user->display_name);

		    try {
			    $result = $apiInstance->sendTransacEmail($sendSmtpEmail);

			    header('Content-type: application/json');
			    echo json_encode( ['state' => 'OK', 'n_points' => intval($_POST['points_to_discount'])] );

			    update_user_meta($current_user->ID, 'loyalty_points', $current_loyalty_points - $_POST['points_to_discount']);
			    wp_die();

		    } catch (Exception $e) {
			    echo 'Exception when calling TransactionalEmailsApi->sendTransacEmail: ', $e->getMessage(), PHP_EOL;
			    wp_die();
		    }
        }
    }
}