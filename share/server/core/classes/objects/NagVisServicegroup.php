<?php
/*****************************************************************************
 *
 * NagVisServicegroup.php - Class of a Servicegroup in NagVis with all necessary
 *                  information which belong to the object handling in NagVis
 *
 * Copyright (c) 2004-2011 NagVis Project (Contact: info@nagvis.org)
 *
 * License:
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2 as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 675 Mass Ave, Cambridge, MA 02139, USA.
 *
 *****************************************************************************/

class NagVisServicegroup extends NagVisStatefulObject {
    protected $type = 'servicegroup';

    protected static $langType = null;
    protected static $langSelf = null;
    protected static $langChild = null;
    protected static $langChild1 = null;

    protected $servicegroup_name;
    protected $alias;

    protected $members = array();

    public function __construct($backend_id, $servicegroupName) {
        $this->backend_id = $backend_id;
        $this->servicegroup_name = $servicegroupName;
        parent::__construct();
    }

    /**
     * Queues the state fetching to the backend.
     */
    public function queueState($_unused_flag = true, $bFetchMemberState = true) {
        global $_BACKEND;
        $queries = Array('servicegroupMemberState' => true);

        if($this->hover_menu == 1
           && $this->hover_childs_show == 1
           && $bFetchMemberState)
            $queries['servicegroupMemberDetails'] = true;

        $_BACKEND->queue($queries, $this);
    }

    /**
     * Applies the fetched state
     */
    public function applyState() {
        if($this->problem_msg) {
            $this->state   = array('ERROR', $this->problem_msg);
            $this->members = Array();
            return;
        }

        if($this->hasMembers()) {
            foreach($this->members AS $MOBJ) {
                $MOBJ->applyState();
            }
        }

        // Use state summaries when some are available to
        // calculate summary state and output
        if($this->aStateCounts !== null) {
            // Calculate summary state and output

            // Only create summary from childs when the summary state is empty.
            // It might be generated by the backend.
            if($this->sum[STATE] === null)
                $this->fetchSummaryStateFromCounts();

            // Only create summary output from childs when the summary output is empty.
            // It might be generated by the backend.
            if($this->sum[OUTPUT] === null)
                $this->fetchSummaryOutputFromCounts();
        } else {
            if($this->sum[STATE] === null)
                $this->fetchSummaryState();

            if($this->sum[OUTPUT] === null)
                $this->fetchSummaryOutput();
        }

        $this->state = $this->sum;
    }

    # End public methods
    # #########################################################################

    /**
     * Fetches the summary state of all members
     */
    private function fetchSummaryState() {
        // Get summary state from member objects
        if($this->hasMembers())
            $this->wrapChildState($this->members);
        else
            $this->sum[STATE] = 'ERROR';
    }

    /**
     * Fetches the summary output from the object state counts
     */
    private function fetchSummaryOutputFromCounts() {
        $arrServiceStates = Array();

        // Loop all major states
        $iSumCount = 0;
        foreach($this->aStateCounts AS $sState => $aSubstates) {
            // Loop all substates (normal,ack,downtime,...)
            foreach($aSubstates AS $sSubState => $iCount) {
                // Found some objects with this state+substate
                if($iCount > 0) {
                    // Count all child objects
                    $iSumCount += $iCount;

                    if(!isset($arrServiceStates[$sState])) {
                        $arrServiceStates[$sState] = $iCount;
                    } else {
                        $arrServiceStates[$sState] += $iCount;
                    }
                }
            }
        }

        
        // FIXME: Recode mergeSummaryOutput method
        $this->mergeSummaryOutput($arrServiceStates, l('services'));

        // Fallback for hostgroups without members
        if($iSumCount == 0) {
            $this->sum[OUTPUT] = l('The servicegroup "[GROUP]" has no members or does not exist (Backend: [BACKEND]).',
                                                                                        Array('GROUP' => $this->getName(),
                                                                                              'BACKEND' => $this->backend_id));
        }
    }

    /**
     * Fetches the summary output from all members
     */
    private function fetchSummaryOutput() {
        if($this->hasMembers()) {
            $arrStates = Array('CRITICAL' => 0, 'DOWN'    => 0, 'WARNING'   => 0,
                               'UNKNOWN'  => 0, 'UP'      => 0, 'OK'        => 0,
                               'ERROR'    => 0, 'PENDING' => 0, 'UNCHECKED' => 0);

            // Get summary state of this and child objects
            foreach($this->members AS &$MEMBER) {
                $arrStates[$MEMBER->getSummaryState()]++;
            }

            $this->mergeSummaryOutput($arrStates, l('services'));
        } else {
            $this->sum[OUTPUT] = l('serviceGroupNotFoundInDB','SERVICEGROUP~'.$this->servicegroup_name);
        }
    }
}
?>
