<?php
namespace GDO\Tests;

use GDO\User\GDO_User;
use function PHPUnit\Framework\assertTrue;
use function PHPUnit\Framework\assertEquals;
use GDO\Core\GDT_Method;

/**
 * Helper Class to test GDOv7 methods.
 * Holds global user objects for test cases.
 * This is ensured by a quirky and important module priority and dependency graph.
 * 
 * @author gizmore
 * @version 7.0.1
 * @since 6.11.2
 */
final class GDT_MethodTest extends GDT_Method
{
    # 0) 2-gizmore (admin)
    # 1) 3-Peter   (staff)
    # 2) 4-Monica  (member)
    # 3) 5-Gaston  (guest)
    # 4) 6-Sven    (staff)
    /**
     * Store some users here for testing.
     * @var GDO_User[]
     */
    public static array $TEST_USERS = [];
    
    ###########
    ### boo ###
    ###########
    /**
     * @deprecated Use ->inputs() method
     */
    public function parameters(array $inputs) : self
    {
    	return $this->inputs($inputs);
    }
    
    ############
    ### Exec ###
    ############
    public function execute(string $button=null)
    {
    	if ($button === null)
    	{
    		$this->clibutton();
    	}
    	else
    	{
    		$this->inputs[$button] = '1';
    	}
    	return parent::execute();
    }

}
