<div class="wrap" id="theme-checklist-admin" data-sync-url="<?php echo wp_nonce_url( admin_url('admin-ajax.php?action=theme_checklist_sync_data') ) ?>">
	<h2><?php _e('Theme Quality Checklist', 'theme-checklist') ?></h2>

	<p class="description">
		<?php
		$theme = wp_get_theme();
		printf( __("You're busy checking the active theme - <strong>%s</strong>. All checks will be saved specifically for that theme."), $theme->Name );
		?>
	</p>

	<?php
	theme_checklist_update_check();
	$checks = theme_checklist_load_checks();

	if(!empty($checks)) {

		$checks_by_category = array();
		$checks_by_category_count = array();
		foreach($checks as $id => $check) {
			if(empty($checks_by_category_count[$check['category']])) {
				$checks_by_category[$check['category']] = array();
				$checks_by_category_count[$check['category']] = 0;
			}

			$checks_by_category[$check['category']][] = $id;
			$checks_by_category_count[$check['category']]++;
		}

		?><ul class="theme-checklist-list" data-reset-url="<?php echo wp_nonce_url( admin_url('admin-ajax.php?action=theme_checklist_reset_all') ) ?>"><?php

		arsort($checks_by_category_count);
		foreach($checks_by_category_count as $category => $count) {

			?><h3><?php echo esc_html($category) ?></h3><?php

			foreach($checks_by_category[$category] as $id) {
				$check = $checks[$id];

				$status_change_url = admin_url( 'admin-ajax.php?action=theme_checklist_change&check='.intval($id) );
				$notes_save_url = admin_url( 'admin-ajax.php?action=theme_checklist_save_notes&check='.intval($id) );

				?>
				<li id="checklist-check-<?php echo intval($check['id']) ?>" data-id="<?php echo intval($check['id']) ?>" data-status="<?php echo isset($check['status']) ? $check['status'] : '' ?>" data-save-notes-url="<?php echo wp_nonce_url($notes_save_url) ?>">
					<div class="result" data-reset-url="<?php echo wp_nonce_url($status_change_url.'&status=reset') ?>">
						<a class="item-pass icon-check <?php if(isset($check['status']) && $check['status'] == 'pass') echo 'result-active' ?>" href="<?php echo wp_nonce_url($status_change_url.'&status=pass') ?>"></a>
						<a class="item-fail icon-cross <?php if(isset($check['status']) && $check['status'] == 'fail') echo 'result-active' ?>" href="<?php echo wp_nonce_url($status_change_url.'&status=fail') ?>"></a>
					</div>

					<div class="more">
						<a href="<?php echo esc_url($check['url']) ?>" class="more-link" target="_blank">
							<span class="icon-open"></span>
							<?php _e('More', 'theme-checklist') ?>
						</a>
					</div>

					<div class="description">
						<h5><?php echo esc_html($check['title']) ?></h5>
						<p><?php echo esc_html($check['excerpt']) ?></p>

						<div class="notes <?php if(!isset($check['status']) || $check['status'] == 'pass') echo 'hidden' ?>">
							<a href="#" class="save-notes" data-save-text="<?php echo esc_attr_e('Save Notes', 'theme-checklist') ?>" data-saving-text="<?php echo esc_attr_e('Saving...', 'theme-checklist') ?>"><?php _e('Save Notes', 'theme-checklist') ?></a>
							<label>Notes:</label>
							<textarea rows="1"><?php if(!empty($check['notes'])) echo esc_textarea($check['notes']) ?></textarea>
						</div>
					</div>

				</li>
				<?php
			}
		}

		?></ul><?php
	}
	else {
		?>
		<div class="error settings-error"><p><strong><?php _e("No checks found.") ?></strong></p></div>
		<?php
	}

	?>

	<div class="import-export">
		<a href="#" class="button import"><?php _e('Import', 'theme-checklist') ?></a>
		<a href="<?php echo admin_url('admin-ajax.php?action=theme_checklist_export') ?>" class="button export"><?php _e('Export', 'theme-checklist') ?></a>
	</div>

	<div id="checklist-import-modal-overlay"></div>
	<div id="checklist-import-modal">
		<textarea data-error-message="<?php esc_attr_e('Problem with json', 'theme-checklist') ?>"></textarea>
		<small class="description"><?php _e('Paste the content of your export json file.') ?></small>

		<a href="#" class="button button-primary button-import"><?php _e('Import', 'theme-checklist') ?></a>
		<a href="#" class="button button-cancel"><?php _e('Cancel', 'theme-checklist') ?></a>
	</div>

	<a href="<?php echo wp_nonce_url(admin_url('admin-ajax.php?action=theme_checklist_view_fix_report')) ?>" class="button-secondary" target="_blank"><?php _e('View Fix Report', 'theme-checklist'); ?></a>
	<a href="#" id="theme-checklist-reset" class="button" data-confirm="<?php esc_attr_e('Are you sure you want to reset all checks?', 'theme-checklist') ?>"><?php _e('Reset All', 'theme-checklist') ?></a>
</div>