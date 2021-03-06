<?php
/**
 * busca-ativa-escolar-api
 * CheckCaseDeadlines.php
 *
 * Copyright (c) LQDI Digital
 * www.lqdi.net - 2017
 *
 * @author Aryel Tupinambá <aryel.tupinamba@lqdi.net>
 *
 * Created at: 16/02/2017, 19:15
 */

namespace BuscaAtivaEscolar\Console\Commands;


use BuscaAtivaEscolar\CaseSteps\CaseStep;
use BuscaAtivaEscolar\Child;
use Carbon\Carbon;

class CheckCaseDeadlines extends Command {

	protected $signature = 'workflow:check_case_deadlines';
	protected $description = 'Checks the case deadlines and updates the status on the database';

	public function handle() {
		$children = Child::getAllActive();
		$today = Carbon::today();

		foreach($children as $child) {
			$step = $child->currentStep; /* @var $step CaseStep */

			$stepDeadline = $child->tenant->getDeadlineFor($step->getSlug());

			$currentStatus = $child->deadline_status;
			$newStatus = $currentStatus;

			// TODO: maybe cache list of tenants for getDeadlineFor? every call is calling unserialize()
			if($step->isLate($today, $stepDeadline)) $newStatus = 'late';

			$this->comment("Processing: {$child->id}: {$stepDeadline} days \t {$step->started_at} \t {$currentStatus} -> {$newStatus}");

			if($currentStatus == $newStatus) continue;

			// TODO: This may have heavy performance penalty due to each save triggering a Child reindex; refactor when on a larger scale

			$child->update(['deadline_status' => $newStatus]);
		}
	}

}