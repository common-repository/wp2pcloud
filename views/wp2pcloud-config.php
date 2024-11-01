<?php 
$auth = wp2pcloud_getAuth();

$days = array(
		'1'=>'Sunday',
		'Monday',
		'Tuesday',
		'Wednesday',
		'Thursday',
		'Friday',
		'Saturday',
);
$freg = wp_get_schedules();
//array (
//		'daily' => array (
//				'interval' => 86400,
//				'display' => 'Daily'
//		),
//		'weekly' => array (
//				'interval' => 604800,
//				'display' => 'Weekly'
//		),
//		'monthly' => array (
//				'interval' => 2419200,
//				'display' => 'Montly'
//		)
//);
$sch_data = wp2pcloud_getSchData();
$next_sch = wp_next_scheduled('run_pcloud_backup_hook');
$imgUrl = rtrim ( WP_PLUGIN_URL, '/' ) . '/' . PCLOUD_DIR."/images/";
?>
<div id="wp2pcloud">

	<?php if(isset($_GET['msg']) && $_GET['msg'] == 'restore_ok') {?>
		<div id="message" class="updated below-h2">
		<p><?php echo __('Your files and database has been restored successfull')?> </p>
	</div>
	<?php } ?>

<!--	<div id="wp2pcloud_restoring" style="display: none;">-->
<!--		<h3>--><?php //echo __('Restoring from archive','wp2pcloud');?><!--</h3>-->
<!---->
<!--		<div style="text-align: center;">-->
<!--			<div id="message" class="updated below-h2">-->
<!--				<p>--><?php //echo __('Please wait, your backup is downloading')?><!-- <img src="--><?php //echo  rtrim ( WP_PLUGIN_URL, '/' ) . '/' . PCLOUD_DIR . '/images/preload.gif'?><!--" alt="" /> <br /> <br />-->
<!--				</p>-->
<!--			</div>-->
<!---->
<!--			<div style="text-align: left; margin-top: 10px;">-->
<!--				--><?php //echo __("When your backup is restored, this page will reload!",'wp2pcloud')?>
<!--			</div>-->
<!--		</div>-->
<!---->
<!--	</div>-->

	<div id="wp2pcloud_settings" class="<?php echo ($auth == false) ? 'login_page' :''; ?>" >
	<?php  if($auth == false) {	 ?>
	
	<div>

		<div class="clear">
			<div style="float: left">
				<h2>Login with your pCloud account</h2>
				<form style="" action="" id="link_pcloud_form">
					<table>
						<tbody>
						<tr>
							<td>Username:</td>
							<td><input autocomplete="off" placeholder="<?php echo __('Your pCloud username','wp2pcloud')?>" type="text" name="username" /></td>
						</tr>
						<tr>
							<td>Password:</td>
							<td><input autocomplete="off" type="password" placeholder="<?php echo __('Your pCloud password','wp2cloud')?>" name="password" /></td>
						</tr>
						<tr>
							<td colspan="2"><input type="submit" name="submit" value="<?php echo __('Link with your pCloud account','wp2pcloud')?>" class="button-secondary" /></td>
						</tr>
						</tbody>
					</table>
				</form>
			</div>

			<div class="help-panel" style="float: right">
				<h2>Dont have pCloud account ?</h2>
				<a href="https://my.pcloud.com/#page=register&ref=1235" target="_blank" class="page-title-action">GET pCloud account</a>
			</div>
		</div>
	
		<div class="clear"></div>
	</div>
	<?php }else { ?>

		<div class="wrap">

			<h1>pCloud Backup</h1>


			<!-- show link info -->
			<div class="updated notice is-dismissible">
				<?php echo __('Your account is linked with pCloud','wp2pcloud')?>
				(<span id="pcloud_info"></span>)
				<a href="#" onclick="unlink_account(jQuery(this));;return false;" ><?php echo __('unlink your account','wp2pcloud')?></a>
			</div>


			<div class="log_show notice is-dismissible" style="border-left:0px;">
			</div>

			<table class="widefat">
				<thead>
				<tr>
					<th scope="col"><?php echo __('Time','wp2pcloud')?></th>
					<th scope="col"><?php echo __('Size','wp2pcloud')?></th>
					<th scope="col"><?php echo __('Actions','wp2pcloud')?></th>
				</tr>

				</thead>
				<tbody id="pcloudListBackups">
				<tr>
					<td colspan="4">This is where your backups will appear once you have some.</td>
				</tr>
				</tbody>
			</table>
		</div>




		<div class="schedule">
			<h4><?php echo __("Next scheduled backup")?></h4>
			<div>
				<?php if(wp_next_scheduled('run_pcloud_backup_hook'))  {
					$t1 = date_create(date('r',wp_next_scheduled('run_pcloud_backup_hook')));
					$t2 = date_create(date('r'));

					if(!function_exists('date_diff')) {
						class DateInterval {
							public $y;
							public $m;
							public $d;
							public $h;
							public $i;
							public $s;
							public $invert;
							public $days;

							public function format($format) {
								$format = str_replace('%R%y',
									($this->invert ? '-' : '+') . $this->y, $format);
								$format = str_replace('%R%m',
									($this->invert ? '-' : '+') . $this->m, $format);
								$format = str_replace('%R%d',
									($this->invert ? '-' : '+') . $this->d, $format);
								$format = str_replace('%R%h',
									($this->invert ? '-' : '+') . $this->h, $format);
								$format = str_replace('%R%i',
									($this->invert ? '-' : '+') . $this->i, $format);
								$format = str_replace('%R%s',
									($this->invert ? '-' : '+') . $this->s, $format);

								$format = str_replace('%y', $this->y, $format);
								$format = str_replace('%m', $this->m, $format);
								$format = str_replace('%d', $this->d, $format);
								$format = str_replace('%h', $this->h, $format);
								$format = str_replace('%i', $this->i, $format);
								$format = str_replace('%s', $this->s, $format);

								return $format;
							}
						}

						function date_diff(DateTime $date1, DateTime $date2) {

							$diff = new DateInterval();

							if($date1 > $date2) {
								$tmp = $date1;
								$date1 = $date2;
								$date2 = $tmp;
								$diff->invert = 1;
							} else {
								$diff->invert = 0;
							}

							$diff->y = ((int) $date2->format('Y')) - ((int) $date1->format('Y'));
							$diff->m = ((int) $date2->format('n')) - ((int) $date1->format('n'));
							if($diff->m < 0) {
								$diff->y -= 1;
								$diff->m = $diff->m + 12;
							}
							$diff->d = ((int) $date2->format('j')) - ((int) $date1->format('j'));
							if($diff->d < 0) {
								$diff->m -= 1;
								$diff->d = $diff->d + ((int) $date1->format('t'));
							}
							$diff->h = ((int) $date2->format('G')) - ((int) $date1->format('G'));
							if($diff->h < 0) {
								$diff->d -= 1;
								$diff->h = $diff->h + 24;
							}
							$diff->i = ((int) $date2->format('i')) - ((int) $date1->format('i'));
							if($diff->i < 0) {
								$diff->h -= 1;
								$diff->i = $diff->i + 60;
							}
							$diff->s = ((int) $date2->format('s')) - ((int) $date1->format('s'));
							if($diff->s < 0) {
								$diff->i -= 1;
								$diff->s = $diff->s + 60;
							}

							$start_ts   = $date1->format('U');
							$end_ts   = $date2->format('U');
							$days     = $end_ts - $start_ts;
							$diff->days  = round($days / 86400);

							if (($diff->h > 0 || $diff->i > 0 || $diff->s > 0))
								$diff->days += ((bool) $diff->invert)
									? 1
									: -1;

							return $diff;

						}

					}


					$diff =date_diff($t1,$t2);




					echo __("Next backup will performed on ",'wp2pcloud').date('r',wp_next_scheduled('run_pcloud_backup_hook'));
					echo "<br />";
					echo 'After '.$diff->format('%i minutes');
				} else {
					echo __("There is no scheduled backups",'wp2pcloud');
				} ?>
			</div>
		</div>


		<div>
			<div style="margin-top: 40px;">
				<a href="#" id="run_wp_backup_now" class="button" onclick="makeBackupNow(jQuery(this));return false;">Make backup now</a>
			</div>
		</div>
		

		<div>
			<h3><?php echo __('Schedule settings','wp2pcloud');?></h3>

			<form action="" id="wp2pcloud_sch">

				<div id="setting-error-settings_updated" class="updated settings-error below-h2" style="margin: 0px; margin-bottom: 10px; display: none;">
					<p><?php echo __('Your settings are saved','wp2pcloud')?></p>
				</div>

				<table>
					<tbody>

						<tr>
							<td>Frequency</td>
							<td><select name="freq" id="freq">
								<?php foreach($freg as $k => $el) { ?>
									<option <?php if(isset($sch_data['freq']) && $sch_data['freq'] == $k ) { echo " selected='selected' "; }?> value="<?php echo $k?>"><?php echo $el['display']?></option>
								<?php  }?>
							</select></td>
						</tr>
					</tbody>
				</table>
				<input type="submit" name="submit" value="<?php echo __('Save settings','wp2pcloud')?>" class="button-primary" />
			</form>
		</div>


	
	<?php } ?>
	</div>
</div>
