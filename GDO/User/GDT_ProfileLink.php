<?php
namespace GDO\User;

use GDO\UI\GDT_Link;
use GDO\Core\GDT_Template;
use GDO\UI\WithAvatar;

/**
 * A link to a profile.
 *  - Requires user()
 *  - Optional avatar()
 *  - Optional nickname()
 *  
 * @author gizmore
 * @version 7.0.0
 */
final class GDT_ProfileLink extends GDT_Link
{
	use WithAvatar;
	
	public string $icon = 'user';
	
	public function hrefProfile() : string
	{
		return href('User', 'Profile', "&id={$this->user->getID()}");
	}
	
	public GDO_User $user;
	public function user(GDO_User $user) : self
	{
		$this->user = $user;
		return $this;
	}
	
	public bool $nickname = false;
	public function nickname(bool $nickname = true) : self
	{
		$this->nickname = $nickname;
		return $this;
	}
	
	public function showNickname() : bool
	{
		return $this->avatar ? $this->nickname : true;
	}
	
	##############
	### Render ###
	##############
	public function renderHTML() : string
	{
		$tVars = [
			'field' => $this,
			'user' => isset($this->user) ? $this->user : GDO_User::current(),
		];
		return GDT_Template::php('User', 'profile_link_html.php', $tVars);
	}
	
}
