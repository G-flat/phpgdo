<?php
namespace GDO\UI;

use GDO\Core\GDT;
use GDO\Core\GDT_Template;

/**
 * A popup shown once after the page has loaded.
 * 
 * @author gizmore
 * @since 6.10.4
 */
final class GDT_Popup extends GDT
{
    use WithText;
    use WithPHPJQuery;
    
    ##############
    ### Render ###
    ##############
    public function renderHTML() : string
    {
        return GDT_Template::php('UI', 'cell/popup.php', [
            'field' => $this,
        ]);
    }
    
    public function renderJSON()
    {
        return $this->renderText();
    }
    
    public function renderCLI() : string
    {
        # Echo instead of return... kinda popup
        echo $this->renderText();
    }
    
}
