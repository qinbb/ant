<?= \Ant::init()->get("top")->draw(); ?>
<div style="padding:50px;background-color:#ddd;">
	<h1>HEADER</h1>

	<?php foreach($ovarahalla as $e):?>
	<?php echo $e;?>
	<?php endforeach;?>
</div>