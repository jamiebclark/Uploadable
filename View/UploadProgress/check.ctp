<div class="uploadprogress">
	<?php if (!empty($uploadProgress)): 
		if (!empty($uploadProgress['done'])) {
			$title = 'Complete';
			$icon  = '<i class="fa fa-check"></i>';
		} else {
			$title = 'Uploading';
			$icon = '<i class="fa fa-spinner fa-spin"></i>';
		}
		?>
		<h2 class="uploadprogress-title">
			<span class="pull-right"><?php echo $icon; ?></span>
			<?php echo $title; ?>
		</h2>
		<div class="uploadprogress-body">
			<?php echo $this->UploadProgress->startTime($uploadProgress['start_time']); ?>
			<?php echo $this->UploadProgress->progressBar($uploadProgress['bytes_processed'], $uploadProgress['content_length']); ?>
			<ul class="list-group">
				<?php foreach ($uploadProgress['files'] as $file): 
					$class = 'list-group-item';
					if ($file['done']) {
						$class .= ' list-group-item-success';
						$icon = '<i class="fa fa-check"></i>';
					} else {
						$icon = '<i class="fa fa-spinner fa-spin"></i>';
					}
					?>
					<li class="<?php echo $class; ?>">
						<span class="pull-right"><?php echo $icon; ?></span>
						<?php echo $file['name']; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php else : ?>
		<h2 class="uploadprogress-title">Complete!</h2>
	<?php endif; ?>
</div>
