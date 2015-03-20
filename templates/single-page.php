<?php
/**
 * Template file for displaying single house hunter
 *
 * @package    WordPress
 * @subpackage House Hunter
 * @author     The Cold Turkey Group
 * @since      1.0.0
 */

global $pf_house_hunter, $wp_query;

$id          = get_the_ID();
$title       = get_the_title();
$permalink   = get_permalink();
$broker      = get_post_meta( $id, 'legal_broker', true );
$cta         = get_post_meta( $id, 'call_to_action', true );
$retargeting = get_post_meta( $id, 'retargeting', true );
$conversion  = get_post_meta( $id, 'conversion', true );
$phone       = of_get_option( 'phone_number' );

// Get the page colors
if ( function_exists( 'of_get_option' ) ) {
	$primary_color = of_get_option( 'primary_color' );
	$hover_color   = of_get_option( 'secondary_color' );
}

$color_setting = get_post_meta( $id, 'primary_color', true );
$hover_setting = get_post_meta( $id, 'hover_color', true );

if ( $color_setting && $color_setting != '' )
	$primary_color = $color_setting;

if ( $hover_setting && $hover_setting != '' )
	$hover_color = $hover_setting;

?>
	<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="utf-8">
		<title><?php wp_title( '&middot;', true, 'right' ); ?></title>
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<?php wp_head(); ?>
		<style>
			<?php
			if( $primary_color != null ) {
				echo '
				.quiz-page .btn-primary {
					background-color: ' . $primary_color . ' !important;
					border-color: ' . $primary_color . ' !important; }
				.modal-body h2 {
					color: ' . $primary_color . ' !important; }
				.quiz-page .question-number {
					color: ' . $primary_color . ' !important; }
				.quiz-completed i {
					color: ' . $primary_color . ' !important; }
				.progress-bar {
				  background-color: ' . $primary_color . ' !important; }
				';
			}
			if( $hover_color != null ) {
				echo '
				.quiz-page .btn-primary:hover,
				.quiz-page .btn-primary:active {
					background-color: ' . $hover_color . ' !important;
					border-color: ' . $hover_color . ' !important; }
				';
			}
			?>
		</style>
		<!--[if lt IE 9]>
		<script src="http://html5shim.googlecode.com/svn/trunk/html5.js"></script>
		<script src="assets/js/respond.min.js"></script>
		<![endif]-->
	</head>

<body <?php body_class(); ?>>
<div id="content" class="hh-page">

	<div class="container-fluid">
		<div class="row page animated fadeIn">
			<div class="col-xs-10 col-xs-offset-1 col-sm-12 col-sm-offset-0 col-md-8 col-md-offset-2" id="landing" data-model="landing">
				<h3 style="text-align: center;" class="landing-title"><?= $title ?></h3>
				<h4 style="text-align: center;">Search up to date listings with accurate information taken directly from the MLS
					database.</h4>

				<form id="house-hunter">
					<div class="form-group">
						<label class="control-label" for="location">Location</label>
						<input type="text" class="form-control" id="location" name="location" placeholder="City and State, Address or Zip Code">
					</div>

					<div class="row">
						<div class="col-xs-12 col-sm-6">
							<label class="control-label">Price</label>

							<div class="form-group">
								<label class="control-label sr-only" for="min_price">Minimum Price</label>

								<div class="input-group">
									<span class="input-group-addon">$</span>
									<input type="text" class="form-control" id="min_price" name="min_price" placeholder="Min Price">
								</div>
							</div>
						</div>
						<div class="col-xs-12 col-sm-6">
							<div class="form-group">
								<label class="control-label sr-only" for="min_price">Maximum Price</label>

								<div class="input-group">
									<span class="input-group-addon">$</span>
									<input type="text" class="form-control" id="max_price" name="max_price" placeholder="Max Price">
								</div>
							</div>
						</div>
					</div>
				</form>

				<div class="row">
					<div class="col-xs-12 col-sm-6">
						<label class="control-label" for="num_beds">Bedrooms</label>
						<input type="text" class="form-control" id="num_beds" name="num_beds" placeholder="Any # Beds">
					</div>
					<div class="col-xs-12 col-sm-6">
						<label class="control-label" for="num_baths">Bathrooms</label>
						<input type="text" class="form-control" id="num_baths" name="num_baths" placeholder="Any # Baths">
					</div>
				</div>

				<input class="btn btn-primary btn-lg" type="submit" value="<?= $cta ?>">
			</div>
		</div>
	</div>

	<div class="footer">
		<?php echo $broker;
		if ( $phone != null ) {
			echo ' &middot; ' . $phone;
		} ?>
	</div>

	<div class="modal fade" id="quiz-offer" tabindex="-1" role="dialog" aria-labelledby="quiz-offer-label" aria-hidden="true">
		<div class="modal-dialog modal-lg">
			<div class="modal-content">
				<div class="modal-body">
					<!-- Popup content -->
				</div>
			</div>
		</div>
	</div>

	<?php
	if ( $retargeting != null )
		echo '<input type="hidden" id="retargeting" value="' . $retargeting . '">';

	if ( $conversion != null )
		echo '<input type="hidden" id="conversion" value="' . $conversion . '">';
	?>
</div>

<?php wp_footer(); ?>