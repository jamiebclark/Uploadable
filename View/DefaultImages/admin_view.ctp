<?php
echo $this->Html->link('Back to list', array('action' => 'index'));
?>
<h2><?php echo $defaultImage['alias']; ?></h2>
<h3>Upload New Image</h3>
<?php
echo $this->Form->create(null, array('type' => 'file'));
echo $this->Form->hidden('model', array('value' => $model));
echo $this->Form->input('default_image', array('type' => 'file', 'label' => 'Select default file'));
echo $this->Form->end('Update');
?>

<h3>Current Image</h3>
<?php
foreach ($defaultImage['dirs'] as $dir) {
	echo $this->Html->tag('h4', $dir);
	echo $this->DefaultImage->image($defaultImage, compact('dir'));
}
?>
