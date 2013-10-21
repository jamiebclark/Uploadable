<table>
<?php foreach ($defaultImages as $model => $row): 
	$url = array('action' => 'view', $model);
	?>
	<tr>
		<td><?php echo $this->DefaultImage->image($row, array('url' => $url, 'width' => 40)); ?></td>
		<td><?php echo $this->Html->link($model, $url); ?></td>
	</tr>
<?php endforeach; ?>
</table>