<table class="table table-layout">
<?php 
$rowCount = 0;
$colWidth = 40;
foreach ($defaultImages as $model => $row): 
	$class = $rowCount++ % 2 == 0 ? 'altrow' : '';
	$url = array('action' => 'view', $model);
	?>
	<tr class="<?php echo $class; ?>">
		<td width=<?php echo $colWidth; ?>><?php echo $this->DefaultImage->image($row, array('url' => $url, 'width' => $colWidth)); ?></td>
		<td><?php echo $this->Html->link($model, $url); ?></td>
	</tr>
<?php endforeach; ?>
</table>