<?php if (!defined('APPLICATION')) die(); 


$DiscussionData = $this->Data('DiscussionData');

?>

<h1><?php echo $this->Data('Title'); ?></h1>

<?php
echo $this->Form->Open(array('class' => 'MoveComemntsForm'));
echo $this->Form->Errors();
?>

<p>
<?php printf(T('You have chosen to move %s into other discussion.'), 
	Plural($this->CountCheckedComments, '%s comment', '%s comments')); ?>
</p>

<ul>
	<li>
		<?php
			echo $this->Form->Label('Discussion Topic', 'Name');
			echo $this->Form->TextBox('Name');
		?>
	</li>	
	<li>
		<?php
			echo $this->Form->Label('Discussion ID', 'DiscussionID');
			echo $this->Form->TextBox('DiscussionID');
		?>
	</li>
	<?php if ($DiscussionData) {
		echo '<li class="CheckedDiscussionID">';
		echo $this->Form->RadioList('CheckedDiscussionID', $DiscussionData, array('ValueField' => 'DiscussionID', 'TextField' => 'Name'));
		echo '</li>';
	}
	?>
</ul>
<?php
echo $this->Form->Button('Move comments');
echo $this->Form->Close();
?>

