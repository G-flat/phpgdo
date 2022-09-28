<?php
namespace GDO\Core;

use GDO\DB\Query;
use GDO\Table\GDT_Table;
use GDO\UI\WithIcon;
use GDO\UI\WithLabel;
use GDO\Table\GDT_Filter;

/**
 * A virtual field that is generated by a subquery.
 * Uses a proxy gdt to render.
 * 
 * You need to provide subquery sql and gdt proxy
 * 
 * @author gizmore
 * @version 7.0.1
 * @since 6.10.0
 * 
 * @see GDT_Join
 */
class GDT_Virtual extends GDT
{
	use WithGDO;
	use WithIcon;
	use WithLabel;
	
	public function isTestable() : bool { return false; }
    public function isVirtual() : bool { return true; }
    public function isSerializable() : bool { return true; }
    public function isOrderable() : bool { return true; }
    
    #############
    ### Query ###
    #############
    public string $subquery;
    public function subquery(string $subquery) : self
    {
    	$this->subquery = $subquery;
    	return $this;
    }
    
    #############
    ### Event ###
    #############
    /**
     * Select this virtual column as subselect.
     */
    public function gdoBeforeRead(GDO $gdo, Query $query) : void
    {
    	if (isset($this->subquery))
        {
            $query->select("({$this->subquery}) AS {$this->getName()}");
        }
    }
    
    #############
    ### Proxy ###
    #############
    /**
     * Encapsulated virtual GDT Proxy
     * @var GDT
     **/
    public $gdtType;
    
    /**
     * Get and setup the proxy GDT
     * @return GDT
     */
    private function proxy()
    {
    	$gdt = $this->gdtType->gdo($this->gdo);
    	if (isset($this->labelKey))
    	{
    		$gdt->label($this->labelKey, $this->labelArgs);
    	}
    	elseif (isset($this->labelRaw))
    	{
    		$gdt->label($this->labelRaw);
    	}
    	return $gdt;
    }
    
    public function gdtType(GDT $gdt) : self
    {
        $this->gdtType = $gdt;
        $this->gdtType->name($this->getName());
//         if (isset($gdt->virtual))
//         {
//             $this->gdtType->virtual = true;
//         }
//         $this->filterable = $gdt->filterable;
//         $this->orderable = $gdt->orderable;
//         $this->searchable = $gdt->searchable;
        return $this;
    }
    
    ##############
    ### Render ###
    ##############
    public function htmlClass() : string { return $this->proxy()->htmlClass(); }

    public function render() { return $this->proxy()->render(); }
    public function renderHTML() : string { return $this->proxy()->renderHTML(); }
    public function renderJSON() { return $this->proxy()->renderJSON(); }
    public function renderCard() : string { return $this->proxy()->renderCard(); }
    public function renderForm() : string { return $this->proxy()->renderForm(); }
    public function renderHeader() : string { return $this->proxy()->renderHeader(); }
    public function renderFilter(GDT_Filter $f) : string { return $this->proxy()->renderFilter($f); }
    
    public function displayTableOrder(GDT_Table $table)
    {
        return $this->proxy()->displayTableOrder($table);
    }
    
    public function filterQuery(Query $query, GDT_Filter $f) : self
    {
        return $this->proxy()->filterQuery($query, $f);
    }

}
