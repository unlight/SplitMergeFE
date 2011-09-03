<?php if (!defined('APPLICATION')) exit();
/*
Copyright 2008, 2009 Vanilla Forums Inc.
This file is part of Garden.
Garden is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version.
Garden is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
You should have received a copy of the GNU General Public License along with Garden.  If not, see <http://www.gnu.org/licenses/>.
Contact Vanilla Forums Inc. at support [at] vanillaforums [dot] com
*/

// Define the plugin:
$PluginInfo['SplitMergeFE'] = array(
	'Name' => 'Split / Merge (FE)',
	'Description' => 'Allows moderators with discussion edit permission to split & merge discussions.',
	'Version' => '1.00',
	'Author' => "Mark O'Sullivan (Fixed by S)",
	'AuthorEmail' => 'mark@vanillaforums.com',
	'AuthorUrl' => 'http://www.vanillaforums.com'
);

class SplitMergeFEPlugin extends Gdn_Plugin {

	/**
	* Add "split" action link.
	*/
	public function Base_BeforeCheckComments_Handler($Sender) {
		$ActionMessage = &$Sender->EventArguments['ActionMessage'];
		$Discussion = $Sender->EventArguments['Discussion'];
		if (Gdn::Session()->CheckPermission('Vanilla.Discussions.Edit', TRUE, 'Category', $Discussion->PermissionCategoryID)) {
			$ActionMessage .= ' '.Anchor(T('Split'), 'vanilla/moderation/splitcomments/'.$Discussion->DiscussionID.'/', 'Split Popup');
			$ActionMessage .= ' ' . Anchor(T('Move'), 'vanilla/moderation/movecomments/'.$Discussion->DiscussionID, 'Move Popup');
		}
	}
	
	/**
	* Add "merge" action link.
	*/
	public function Base_BeforeCheckDiscussions_Handler($Sender) {
		$ActionMessage = &$Sender->EventArguments['ActionMessage'];
		if (Gdn::Session()->CheckPermission('Vanilla.Discussions.Edit', TRUE, 'Category', 'any'))
			$ActionMessage .= ' '.Anchor(T('Merge'), 'vanilla/moderation/mergediscussions/', 'Merge Popup');
	}
	
	
	// LATER: TODO (I HATE THIS CHECKBOX STYLE MARKING)
	
	/**
	* Undocumented 
	* 
	*/
/*	public function DiscussionController_CommentOptions_Handler($Sender) {
		$Sender->CanEditComments = False;
		$Object =& $Sender->EventArguments['Comment'];
		//$PermissionCategoryID = GetValue('PermissionCategoryID', $Object, GetValue('PermissionCategoryID', $Sender->Discussion));
		if (Gdn::Session()->CheckPermission('Vanilla.Comments.Edit', TRUE, 'Category', 'any')) {
		}
	}*/
	
	/**
	* Undocumented 
	* 
	*/
	public function ModerationController_Render_Before($Sender) {
		$Sender->AddJsFile('split-merge-fe.js', 'plugins/SplitMergeFE');
	}
	
	public function DiscussionController_Render_Before($Sender) {
		$Sender->AddJsFile('split-merge-fe.js', 'plugins/SplitMergeFE');
	}
	
	/**
	* Undocumented 
	* 
	*/
	public function ModerationController_AutoCompleteDiscussionName_Create($Sender) {
		$DiscussionModel = new DiscussionModel();
		$CategoryPermissions = $DiscussionModel->CategoryPermissions();
		if ($CategoryPermissions !== TRUE) $DiscussionModel->SQL->WhereIn('d.CategoryID', $CategoryPermissions);
		$Discussions = $DiscussionModel->SQL
			->Select('d.Name, d.DiscussionID')
			->From($DiscussionModel->Name. ' d')
			->Like('d.Name', GetIncomingValue('q'))
			->Limit(10)
			->Get();
		foreach ($Discussions as $Discussion) {
			echo $Discussion->Name, '|', $Discussion->DiscussionID, "\n";
		}
	}
	
	/**
	* Move comments to other discussion.
	* 
	* @param Gdn_Controller $Sender
	* @return NULL.
	*/
	public function ModerationController_MoveComments_Create($Sender, $RequestArgs = NULL) {
		$DiscussionID = $RequestArgs[0];
		$DiscussionModel = new DiscussionModel();
		$Discussion = $DiscussionModel->GetID($DiscussionID);
		if (!$Discussion) return;
		$DiscussionID = $Discussion->DiscussionID;
		$Sender->Form = new Gdn_Form();
		$Sender->Permission('Vanilla.Discussions.Edit', TRUE, 'Category', $Discussion->PermissionCategoryID);
		$Sender->Title(T('Move comments'));
		$Session = Gdn::Session();
		$CheckedComments = $Session->GetAttribute('CheckedComments');
		if (!is_array($CheckedComments)) $CheckedComments = array();
		$DiscussionCommentIDs = GetValue($DiscussionID, $CheckedComments);
		$Sender->SetData('CountCheckedComments', count($DiscussionCommentIDs), True);
		
		$CheckedDiscussions = $Session->GetAttribute('CheckedDiscussions', array());
		if (!is_array($CheckedDiscussions)) $CheckedDiscussions = array();
		if (count($CheckedDiscussions) > 0) $Sender->SetData('DiscussionData', $DiscussionModel->GetIn($CheckedDiscussions));
		
		if ($Sender->Form->AuthenticatedPostBack()) {
			$FormValues = $Sender->Form->FormValues();
			$ResultDiscussionID = $FormValues['DiscussionID'];
			// Make sure that discussion exists.
			$ResultDiscussion = $DiscussionModel->GetID($ResultDiscussionID);
			if (!$ResultDiscussion) $Sender->Form->AddError(T('%s not found.'), T('discussion'));
			if ($ResultDiscussion && !$Session->CheckPermission('Vanilla.Discussions.Edit', True, 'Category', $ResultDiscussion->PermissionCategoryID))
				$Sender->Form->AddError(T('ErrorPermission'));
			
			if ($Sender->Form->ErrorCount() == 0) {
				$ResultDiscussionID = $ResultDiscussion->DiscussionID;
				$CommentModel = new CommentModel();
				$CommentModel->SQL
					->Update($CommentModel->Name)
					->Set('DiscussionID', $ResultDiscussionID)
					->WhereIn('CommentID', $DiscussionCommentIDs)
					->Put();
				// Update comment counts for new and old discussion.
				$CommentModel->UpdateCommentCount($DiscussionID);
				$CommentModel->UpdateCommentCount($ResultDiscussionID);
				
				// Clear selections
				unset($CheckedComments[$DiscussionID]);
				Gdn::UserModel()->SaveAttribute($Session->UserID, 'CheckedComments', $CheckedComments);
				ModerationController::InformCheckedComments($Sender);
				$Sender->RedirectUrl = Url('discussion/'.$ResultDiscussionID);
			}
		}
		
		$Sender->View = $this->GetView('movecomments.php');
		$Sender->Render();
	}

	/**
	* Add a method to the ModerationController to handle splitting comments out to a new discussion.
	*/
	public function ModerationController_SplitComments_Create($Sender) {
		$Session = Gdn::Session();
		$Sender->Form = new Gdn_Form();
		$Sender->Title(T('Split Comments'));
		$Sender->Category = FALSE;

		$DiscussionID = GetValue('0', $Sender->RequestArgs, '');
		if (!is_numeric($DiscussionID))
			return;
		
		$DiscussionModel = new DiscussionModel();
		$Discussion = $DiscussionModel->GetID($DiscussionID);
		if (!$Discussion)
			return;
		
		// Verify that the user has permission to perform the split
		$Sender->Permission('Vanilla.Discussions.Edit', TRUE, 'Category', $Discussion->PermissionCategoryID);
		
		$CheckedComments = Gdn::Session()->GetAttribute('CheckedComments', array());
		
		if (!is_array($CheckedComments))
			$CheckedComments = array();
		
		$CommentIDs = array();
		foreach ($CheckedComments as $DiscID => $Comments) {
			foreach ($Comments as $Comment) {
				if ($DiscID == $DiscussionID)
					$CommentIDs[] = str_replace('Comment_', '', $Comment);
			}
		}
		// Load category data.
		$Sender->ShowCategorySelector = (bool)C('Vanilla.Categories.Use');
		if ($Sender->ShowCategorySelector) {
			$CategoryModel = new CategoryModel();
			$CategoryData = $CategoryModel->GetFull('', 'Vanilla.Discussions.Add');
			$aCategoryData = array();
			foreach ($CategoryData->Result() as $Category) {
				if ($Category->CategoryID <= 0)
					continue;
				
				if ($Discussion->CategoryID == $Category->CategoryID)
					$Sender->Category = $Category;
				
				$CategoryName = $Category->Name;   
				if ($Category->Depth > 1) {
					$CategoryName = '↳ '.$CategoryName;
					$CategoryName = str_pad($CategoryName, strlen($CategoryName) + $Category->Depth - 2, ' ', STR_PAD_LEFT);
					$CategoryName = str_replace(' ', '&#160;', $CategoryName);
				}
				$aCategoryData[$Category->CategoryID] = $CategoryName;
				$Sender->EventArguments['aCategoryData'] = &$aCategoryData;
					$Sender->EventArguments['Category'] = &$Category;
					$Sender->FireEvent('AfterCategoryItem');
			}
			$Sender->CategoryData = $aCategoryData;
		}
		
		$CountCheckedComments = count($CommentIDs);
		$Sender->SetData('CountCheckedComments', $CountCheckedComments);
		// Perform the split
		if ($Sender->Form->AuthenticatedPostBack()) {
			// Create a new discussion record
			$Data = $Sender->Form->FormValues();
			$Data['Body'] = sprintf(T('This discussion was created from comments split from: %s.'), Anchor(Gdn_Format::Text($Discussion->Name), 'discussion/'.$Discussion->DiscussionID.'/'.Gdn_Format::Url($Discussion->Name).'/'));
			$Data['Format'] = 'Html';
			$NewDiscussionID = $DiscussionModel->Save($Data);
			$Sender->Form->SetValidationResults($DiscussionModel->ValidationResults());
			
			if ($Sender->Form->ErrorCount() == 0 && $NewDiscussionID > 0) {
				// Re-assign the comments to the new discussion record
				$DiscussionModel->SQL
					->Update('Comment')
					->Set('DiscussionID', $NewDiscussionID)
					->WhereIn('CommentID', $CommentIDs)
					->Put();
				
				// Update counts on both discussions
				$CommentModel = new CommentModel();
				$CommentModel->UpdateCommentCount($DiscussionID);
//            $CommentModel->UpdateUserCommentCounts($DiscussionID);
				$CommentModel->UpdateCommentCount($NewDiscussionID);
	
				// Clear selections
				unset($CheckedComments[$DiscussionID]);
				Gdn::UserModel()->SaveAttribute($Session->UserID, 'CheckedComments', $CheckedComments);
				ModerationController::InformCheckedComments($Sender);
				$Sender->RedirectUrl = Url('discussion/'.$NewDiscussionID.'/'.Gdn_Format::Url($Data['Name']));
			}
		} else {
			$Sender->Form->SetValue('CategoryID', GetValue('CategoryID', $Discussion));
		}
		
		$Sender->Render($this->GetView('splitcomments.php'));
	}

	/**
	* Add a method to the ModerationController to handle merging discussions.
	*/
	public function ModerationController_MergeDiscussions_Create($Sender) {
		$Session = Gdn::Session();
		$Sender->Form = new Gdn_Form();
		$Sender->Title(T('Merge Discussions'));

		$DiscussionModel = new DiscussionModel();
		$CheckedDiscussions = $Session->GetAttribute('CheckedDiscussions', array());
		if (!is_array($CheckedDiscussions))
			$CheckedDiscussions = array();
		
		$DiscussionIDs = $CheckedDiscussions;
		$Sender->SetData('DiscussionIDs', $DiscussionIDs);
		$CountCheckedDiscussions = count($DiscussionIDs);
		$Sender->SetData('CountCheckedDiscussions', $CountCheckedDiscussions);
		$DiscussionData = $DiscussionModel->GetIn($DiscussionIDs);
		$Sender->SetData('DiscussionData', $DiscussionData);
		
		// Perform the merge
		if ($Sender->Form->AuthenticatedPostBack()) {
			// Create a new discussion record
			$MergeDiscussion = FALSE;
			$MergeDiscussionID = $Sender->Form->GetFormValue('MergeDiscussionID');
			foreach ($DiscussionData->Result() as $Discussion) {
				if ($Discussion->DiscussionID == $MergeDiscussionID) {
					$MergeDiscussion = $Discussion;
					break;
				}
			}
			if ($MergeDiscussion) {
				// Verify that the user has permission to perform the merge
				$Sender->Permission('Vanilla.Discussions.Edit', TRUE, 'Category', $MergeDiscussion->PermissionCategoryID);
				
				// Assign the comments to the new discussion record
				$DiscussionModel->SQL
					->Update('Comment')
					->Set('DiscussionID', $MergeDiscussionID)
					->WhereIn('DiscussionID', $DiscussionIDs)
					->Put();
					
				$CommentModel = new CommentModel();
				foreach ($DiscussionIDs as $DiscussionID) {
					
					// Add a new comment to each empty discussion
					if ($DiscussionID != $MergeDiscussionID) {
						// Add a comment to each one explaining the merge
						$DiscussionAnchor = Anchor(
							Gdn_Format::Text($MergeDiscussion->Name),
							'discussion/'.$MergeDiscussionID.'/'.Gdn_Format::Url($MergeDiscussion->Name)
						);
						$CommentModel->Save(array(
							'DiscussionID' => $DiscussionID,
							'Body' => sprintf(T('This discussion was merged into %s'), $DiscussionAnchor),
							'Format' => 'Html'
						));
						// Close non-merge discussions
						$CommentModel->SQL->Update('Discussion')->Set('Closed', '1')->Where('DiscussionID', $DiscussionID)->Put();
					}
	
					// Update counts on all affected discussions
					$CommentModel->UpdateCommentCount($DiscussionID);
//               $CommentModel->UpdateUserCommentCounts($DiscussionID);
				}
	
				// Clear selections
				Gdn::UserModel()->SaveAttribute($Session->UserID, 'CheckedDiscussions', FALSE);
				ModerationController::InformCheckedDiscussions($Sender);
				$Sender->RedirectUrl = Url('discussion/'.$MergeDiscussionID.'/'.Gdn_Format::Url($MergeDiscussion->Name));
			}
		}
		
		$Sender->Render($this->GetView('mergediscussions.php'));
	}

	public function Setup() {
		RemoveFromConfig('EnabledPlugins.SplitMerge');
		SaveToConfig('Vanilla.AdminCheckboxes.Use', TRUE);
	}
	
}