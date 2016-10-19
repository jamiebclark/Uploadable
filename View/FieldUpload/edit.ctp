<?php 
$tabs = [
	'Resize' => $this->element('Uploadable.field_upload_images/resize'),
	'Upload' => $this->element('Uploadable.field_upload_images/upload')
];
if (empty($result[$alias]['uploadable'][$field]['sizes'][$size]) || !empty($result[$alias]['uploadable'][$field]['isDefault'])) {
	unset($tabs['Resize']);
}

$tabTitles = array_keys($tabs);
$tabValues = array_values($tabs);
$activeTab = 0;
?>
<div>
	<?php if (count($tabs) > 1): ?>
		<ul role="tablist" class="nav nav-tabs">
			<?php foreach ($tabTitles as $k => $tabTitle): 
				$id = 'tab-' . $k;
				$class = $k == $activeTab ? 'active' : null;
				echo $this->Html->tag('li',
					$this->Html->link($tabTitle, '#' . $id, [
							'aria-controls' => $id,
							'role' => 'tab',
							'data-toggle' => 'tab',
						]), [
						'role' => 'presentation',
						'class' => $class,
					]);
			endforeach; ?>
		</ul>
	<?php endif; ?>
	<div class="tab-content">
		<?php foreach ($tabValues as $k => $content):
			$id = 'tab-' . $k;
			$class = $k == $activeTab ? 'active' : '';
			$class .= ' tab-pane';
			echo $this->Html->div($class, $content, [
				'role' => 'tabpanel',
				'id' => $id,
			]);
		endforeach; ?>
	</div>
</div>
