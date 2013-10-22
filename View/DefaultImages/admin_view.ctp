<?php
echo $this->Html->link('Back to list', array('action' => 'index'));
?>
<h2><?php echo Inflector::humanize(Inflector::underscore($defaultImage['alias'])); ?></h2>
<div class="row-fluid">
	<div class="span6">
		<dl>
			<dt>Root</dt>
			<dd><?php echo $defaultImage['root']; ?></dd>
			
			<dt>Sub-Directories</dt>
			<dd><?php 
			$start = '["';
			$end = '"]';
			echo $start . implode("$end, $start", $defaultImage['dirs']) . $end; 
			?></dd>
		</dl>
		<fieldset>
			<legend>Upload New Image</legend>
			<?php
			echo $this->Form->create(null, array('type' => 'file'));
			echo $this->Form->hidden('model', array('value' => $model));
			echo $this->Form->input('default_image', array('type' => 'file', 'label' => 'Select default file'));
			echo $this->Form->end('Update');
			?>
		</fieldset>
	</div>
	<div class="span6">
		<h3>Current Images</h3>
		<?php
		foreach ($defaultImage['dirs'] as $dir) {
			echo $this->Html->tag('h4', $dir);
			echo $this->DefaultImage->image($defaultImage, compact('dir'));
		}
		?>
	</div>
</div>
