<?php if ( !defined( 'ABSPATH' ) ) die();
if ( !current_user_can( 'manage_options' ) ) {
	wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}
?>

<div class="wrap">
	<h2>Underbloggar som ska visas på ettan</h2>

	<?php if ( count( $errors ) > 0 ) : ?>
	<?php foreach ( $errors as $error ) : ?>
		<div class="error below-h2"><p><?php echo $error ?></p></div>
		<?php endforeach ?>
	<?php endif ?>

	<?php if ( count( $messages ) > 0 ) : ?>
	<?php foreach ( $messages as $message ) : ?>
		<div class="updated below-h2"><p><?php echo $message ?></p></div>
		<?php endforeach ?>
	<?php endif ?>

	<?php if ( count( $sites ) > 0 ) : ?>

	<h3>Befintliga sajter</h3>

	<table class="widefat">
		<thead>
		<tr>
			<th>Namn</th>
			<th>Adress</th>
			<th>Status</th>
			<th>Senaste hämtning</th>
			<th>Senast uppdaterad</th>
			<th>Antal inlägg</th>
			<th>Radera</th>
		</tr>
		</thead>
		<tbody>
			<?php foreach ( $sites as $key => $site ) : ?>
		<tr>
			<td><?php echo $site->name ?></td>
			<td>
				<a href="<?php echo $site->url ?>" target="_blank"><?php echo $site->url ?></a>
			</td>
			<td><?php echo $site->status ?></td>
			<td><?php echo $site->lastupdate ?></td>
			<td><?php echo strlen( $site->lastbuild ) > 0 ? date( 'Y-m-d H:i:s', strtotime( $site->lastbuild ) ) : '' ?></td>
			<td><?php echo $site->posts ?></td>
			<td>
				<form action="" method="post">
					<?php wp_nonce_field( 'pp-ettan-rm-site-' . $key ) ?>
					<input type="hidden" name="key" value="<?php echo $key ?>">
					<input type="submit" value="Radera">
				</form>
			</td>
		</tr>
			<?php endforeach ?>
		</tbody>
		<tfoot>
		<tr>
			<th>Namn</th>
			<th>Adress</th>
			<th>Status</th>
			<th>Senaste hämtning</th>
			<th>Senast uppdaterad</th>
			<th>Antal inlägg</th>
			<th>Radera</th>
		</tr>
		</tfoot>
	</table>

	<?php endif ?>

	<h3>Lägg till ny sajt</h3>

	<form action="" method="post">
		<?php wp_nonce_field( 'pp-ettan-add-site' ) ?>

		<table class="form-table">
			<tbody>
			<tr>
				<th>
					<label for="pp1-url">Adress</label>
				</th>
				<td>
					<input type="url" name="url" id="pp1-url" placeholder="http://example.com/" required>
				</td>
			</tr>
			<tr>
				<th>
					<label for="pp1-name">Namn</label>
				</th>
				<td>
					<input type="text" name="name" id="pp1-name" required>
				</td>
			</tr>
			</tbody>
		</table>
		<p class="submit">
			<input type="submit" class="button-primary" value="Lägg till">
		</p>
	</form>

	<?php if ( isset( $posts ) && is_array( $posts ) && count( $posts ) > 0 ) : ?>
	<h2>Inlägg</h2>

	<form action="" method="post">

		<?php wp_nonce_field( 'pp-ettan-edit-posts' ) ?>

		<table class="widefat">
			<thead>
			<tr>
				<th>Titel</th>
				<th>Publicerad</th>
				<th>Blogg</th>
				<th>Klistrad</th>
			</tr>
			</thead>
			<tbody>
				<?php foreach ( $posts as $post ) : ?>
			<tr>
				<td><?php echo $post->title ?></td>
				<td><?php echo date( 'Y-m-d H:i:s', strtotime( $post->post_date ) ) ?></td>
				<td><?php $site = $this->get_site( $post->ID ); echo $site->name ?></td>
				<td><input type="checkbox" name="sticky[]"
						   value="<?php echo $post->ID ?>" <?php if ( $post->sticky ) echo 'checked="checked"' ?>></td>
			</tr>
				<?php endforeach ?>
			</tbody>
			<tfoot>
			<tr>
				<th>Titel</th>
				<th>Publicerad</th>
				<th>Blogg</th>
				<th>Klistrad</th>
			</tr>
			</tfoot>
		</table>
		<p class="submit">
			<input type="submit" class="button-primary" value="Spara">
		</p>
	</form>

	<?php endif ?>


	<h2>Övrigt</h2>

	<form action="" method="post">

		<?php wp_nonce_field( 'pp-ettan-other' ) ?>

		<input type="submit" name="submit" class="button" value="Hämta inlägg">
		<em>Hämtar aktuella inlägg från alla underbloggar manuellt</em>

	</form>
</div>