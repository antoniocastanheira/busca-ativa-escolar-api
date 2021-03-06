<?php
/**
 * busca-ativa-escolar-api
 * ReindexAllChildren.php
 *
 * Copyright (c) LQDI Digital
 * www.lqdi.net - 2017
 *
 * @author Aryel Tupinambá <aryel.tupinamba@lqdi.net>
 *
 * Created at: 23/01/2017, 24:25
 */

namespace BuscaAtivaEscolar\Console\Commands;


use BuscaAtivaEscolar\Child;

class ReindexAllChildren extends Command {

	protected $signature = 'maintenance:reindex_all_children';
	protected $description = 'Forces all children in the system to be reindex in ElasticSearch';

	public function handle() {
		$children = Child::with('tenant')->get();

		foreach($children as $child) {
			$this->comment("Reindexing: {$child->tenant->name} / {$child->id} -> {$child->name}");
			$child->save();
		}

		$this->comment("All children reindexed!");

	}

}