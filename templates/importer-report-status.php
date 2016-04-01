<script type="text/javascript">
	parent.document.getElementById('<?php echo $id; ?>-status').innerHTML = '<?php echo $status; ?>';
	parent.document.getElementById('<?php echo $id; ?>').className = 'done';
	if ( typeof parent.importNext === 'function' ) {
		parent.importNext();
	} else {
		alert( 'error importing the next file' );
	}
</script>
