<?php
class UploadProgressHelper extends AppHelper {

	public $helpers = array('Form', 'Html');

	public function formCreate($model = null, $options = array(), $key = null) {
		if (empty($key)) {
			$key = uniqid();
			$key = 123;
		}
		$options = array_merge(array(
			'type' => 'file',
			'data-submitted-overlay-url' => Router::url(array(
				'plugin' => 'uploadable',
				'controller' => 'upload_progress',
				'action' => 'check',
				$key
			)),
			'data-submitted-overlay-refresh' => 500,
		), $options);

		$options = $this->addClass($options, 'submitted-overlay');

		$out = $this->Form->create($model, $options);
		$out .= $this->formKey($key);
		return $out;
	}

	public function formKey($key) {
		$name = ini_get('session.upload_progress.name');
		return $this->Form->hidden($name, array('value' => $key, 'name' => $name));
	}

	public function startTime($time) {
		if (is_array($time)) {
			$time = $time['start_time'];
		}
		$startDate = new DateTime(date('Y-m-d H:i:s', $time));
		$diffDate = $startDate->diff(new DateTime());

		$keyOrder = array(
			'y' => 'years', 
			'm' => 'months', 
			'd' => 'days', 
			'h' => 'hours', 
			'i' => 'minutes', 
			's' => 'seconds'
		);
		$out = '';
		foreach ($keyOrder as $key => $label) {
			$val = $diffDate->{$key};
			if (!empty($out) || !empty($val)) {
				if (!empty($out)) {
					$out .= ' ';
				}
				$out .= "$val $label";
			}
		}
		return $out;
	}

	public function progressBar($currentValue, $maxValue, $class = '') {
		$pct = round($currentValue / $maxValue * 100);
		$content = "$pct%" . $this->Html->tag('span', "$pct% Complete", array('class' => 'sr-only'));
		$class .= (!empty($class) ? ' ' : '') . 'progress-bar';
		return $this->Html->div('progress', 
			$this->Html->div($class, $content, array(
				'role' => 'progressbar',
				'aria-valuenow' => $pct,
				'aria-valuemin' => 0,
				'aria-valuemax' => 100,
				'style' => "width: $pct%"
			))
		);
	}
}