<?php
/**
 * busca-ativa-escolar-api
 * PesquisaCaseStep.php
 *
 * Copyright (c) LQDI Digital
 * www.lqdi.net - 2016
 *
 * @author Aryel Tupinambá <aryel.tupinamba@lqdi.net>
 *
 * Created at: 29/12/2016, 13:51
 */

namespace BuscaAtivaEscolar\CaseSteps;

use BuscaAtivaEscolar\Data\CaseCause;
use BuscaAtivaEscolar\Data\Gender;
use BuscaAtivaEscolar\Data\GuardianType;
use BuscaAtivaEscolar\Data\HandicappedRejectReason;
use BuscaAtivaEscolar\Data\IncomeRange;
use BuscaAtivaEscolar\Data\PlaceKind;
use BuscaAtivaEscolar\Data\Race;
use BuscaAtivaEscolar\Data\SchoolGrade;
use BuscaAtivaEscolar\Data\SchoolingLevel;
use BuscaAtivaEscolar\Data\SchoolLastStatus;
use BuscaAtivaEscolar\Data\WorkActivity;
use BuscaAtivaEscolar\FormBuilder\CanGenerateForms;
use BuscaAtivaEscolar\FormBuilder\FormBuilder;
use BuscaAtivaEscolar\IBGE\UF;
use BuscaAtivaEscolar\User;
use Illuminate\Database\Eloquent\Builder;

class Pesquisa extends CaseStep implements CanGenerateForms {

	protected $table = "case_steps_pesquisa";

	public $stepFields = [
		'name',
		'gender',
		'race',
		'dob',
		'rg',
		'cpf',

		'has_been_in_school',
		'reason_not_enrolled',

		'school_last_grade',
		'school_last_year',
		'school_last_id',
		'school_last_name',
		'school_last_status',
		'school_last_age',
		'school_last_address',

		'is_working',
		'work_activity',
		'work_activity_other',
		'work_is_paid',
		'work_weekly_hours',

		'parents_has_mother',
		'parents_has_father',
		'parents_has_brother',

		'parents_who_is_guardian',
		'parents_income',
		'mother_name',

		'guardian_name',
		'guardian_rg',
		'guardian_cpf',
		'guardian_dob',
		'guardian_phone',
		'guardian_race',
		'guardian_schooling',
		'guardian_job',

		'case_cause_ids',

		'handicapped_at_sus',
		'handicapped_reason_not_enrolled',

		'place_address',
		'place_cep',
		'place_reference',
		'place_neighborhood',
		'place_city_id',
		'place_city_name',
		'place_uf',
		'place_kind',
		'place_is_quilombola',
		'place_lat',
		'place_lng',
		'place_map_region',
		'place_map_geocoded_address',

	];

	protected $casts = [
		'case_cause_ids' => 'array',
		'place_map_geocoded_address' => 'array',
	];

	public function applyAssignableUsersFilter(Builder $query) {
		return $query->whereIn('type', [User::TYPE_TECNICO_VERIFICADOR, User::TYPE_SUPERVISOR_INSTITUCIONAL, User::TYPE_GESTOR_OPERACIONAL]);
	}

	protected function onStart($prevStep = null) {
		if($prevStep instanceof CaseStep) {
			$this->fill(
				collect($prevStep->toArray())
					->except($this->baseFillable)
					->toArray()
			);
			$this->save();
		}

		$this->flagAsPendingAssignment();
	}

	protected function onComplete() : bool {
		// While on the front-end we technically save before completing, this may not always be true
		$this->onUpdated();

		return true;
	}

	protected function onUpdated() {

		if($this->gender) $this->child->gender = $this->gender;
		if($this->name) $this->child->name = $this->name;
		if($this->mother_name) $this->child->mother_name = $this->mother_name;
		if($this->father_name) $this->child->father_name = $this->father_name;

		$this->child->save();

		if($this->case_cause_ids) {
			$this->childCase->case_cause_ids = $this->case_cause_ids;
			$this->childCase->save();
		}

		if($this->dob) {
			$this->child->recalculateAgeThroughBirthday($this->dob);
		}

		if($this->place_address && $this->place_city_name && $this->place_uf) {
			$address = $this->child->updateCoordinatesThroughGeocoding("{$this->place_address} - {$this->place_city_name} - {$this->place_uf}");

			$this->update([
				'place_lat' => ($address) ? $address->getLatitude() : null,
				'place_lng' => ($address) ? $address->getLongitude() : null,
				'place_map_region' => ($address) ? $address->getSubLocality() : null,
				'place_map_geocoded_address' => ($address) ? $address->toArray() : null,
			]);

		}
	}

	public function validate($data, $isCompletingStep = false) {
		$data['is_completing_step'] = $isCompletingStep;

		return validator($data, [
			'name' => 'required_for_completion',
			'gender' => 'required_for_completion|' . \BuscaAtivaEscolar\Data\Gender::getSlugValidationMask(),
			'race' => 'required_for_completion|' . \BuscaAtivaEscolar\Data\Race::getSlugValidationMask(),
			'dob' => 'required_for_completion|date',
			'rg' => 'nullable|alpha_num',
			'cpf' => 'nullable|digits:11',

			'has_been_in_school' => 'required_for_completion|boolean',
			'reason_not_enrolled' => 'nullable|required_if:has_been_in_school,0|string',

			'school_last_grade' => 'nullable|required_if:has_been_in_school,1|' . \BuscaAtivaEscolar\Data\SchoolGrade::getSlugValidationMask(),
			'school_last_year' => 'nullable|required_if:has_been_in_school,1|digits:4',
			'school_last_id' => 'nullable|required_if:has_been_in_school,1|string',
			'school_last_name' => 'nullable|required_if:has_been_in_school,1|string',
			'school_last_status' => 'nullable|required_if:has_been_in_school,1|string',
			'school_last_age' => 'nullable|required_if:has_been_in_school,1|numeric',
			'school_last_address' => 'nullable|required_if:has_been_in_school,1|string',

			'is_working' => 'required_for_completion|boolean',
			'work_activity' => 'nullable|required_if:is_working,1|' . \BuscaAtivaEscolar\Data\WorkActivity::getSlugValidationMask(),
			'work_activity_other' => 'nullable|required_if:work_activity,other|string',
			'work_is_paid' => 'nullable|required_if:is_working,1|boolean',
			'work_weekly_hours' => 'nullable|required_if:is_working,1|numeric',

			'parents_has_mother' => 'nullable|boolean',
			'parents_has_father' => 'nullable|boolean',
			'parents_has_brother' => 'nullable|boolean',

			'parents_who_is_guardian' => 'required_for_completion|in:mother,father,brother',
			'parents_income' => 'nullable|string',
			'mother_name' => 'required_for_completion|string',

			'guardian_name' => 'required_for_completion|string',
			'guardian_rg' => 'nullable|alpha_num',
			'guardian_cpf' => 'nullable|digits:11',
			'guardian_dob' => 'nullable|date',
			'guardian_phone' => 'nullable|alpha_dash',
			'guardian_race' =>  'nullable|' . \BuscaAtivaEscolar\Data\Race::getSlugValidationMask(),
			'guardian_schooling' =>  'nullable|' . \BuscaAtivaEscolar\Data\SchoolingLevel::getSlugValidationMask(),
			'guardian_job' => 'nullable|string',

			'case_cause_ids' => 'array|min:1',

			'handicapped_at_sus' => 'nullable|boolean',
			'handicapped_reason_not_enrolled' => 'nullable|required_if:handicapped_at_sus,1|string',

			'place_address' => 'required_for_completion|string',
			'place_cep' => 'nullable|digits:8',
			'place_reference' => 'nullable|string',
			'place_neighborhood' => 'required_for_completion|string',
			'place_city_id' => 'required_for_completion|string',
			'place_city_name' => 'required_for_completion|string',
			'place_uf' => 'required_for_completion|string|size:2',
			'place_kind' => 'required_for_completion|in:urban,rural',
			'place_is_quilombola' => 'nullable|boolean',
		]);
	}

	public static function getFormFields(): FormBuilder {
		return (new FormBuilder())
			->group('personal', trans('form_builder.pesquisa.group.personal'), function (FormBuilder $group) {
				return $group
				->field('name', 'string', trans('form_builder.pesquisa.field.name'))
				->field('gender', 'select', trans('form_builder.pesquisa.field.gender'), ['options' => Gender::getAllAsArray(), 'key' => 'slug', 'label' => 'label'])
				->field('race', 'select', trans('form_builder.pesquisa.field.race'), ['options' => Race::getAllAsArray(), 'key' => 'slug', 'label' => 'label'])
				->field('dob', 'date', trans('form_builder.pesquisa.field.dob'))
				->field('rg', 'alphanum', trans('form_builder.pesquisa.field.rg'))
				->field('cpf', 'alphanum', trans('form_builder.pesquisa.field.cpf'));
			})

			->group('school', trans('form_builder.pesquisa.group.school'), function (FormBuilder $group) {
				return $group
				->field('has_been_in_school', 'boolean', trans('form_builder.pesquisa.field.has_been_in_school'))
				->field('reason_not_enrolled', 'multiline', trans('form_builder.pesquisa.field.reason_not_enrolled'), ['show_if_false' => 'has_been_in_school'])
				->field('school_last_grade', 'select', trans('form_builder.pesquisa.field.school_last_grade'), ['show_if_true' => 'has_been_in_school', 'options' => SchoolGrade::getAllAsArray(), 'key' => 'slug', 'label' => 'label'])
				->field('school_last_year', 'number', trans('form_builder.pesquisa.field.school_last_year'), ['show_if_true' => 'has_been_in_school'])
				->field('school_last_id', 'model', trans('form_builder.pesquisa.field.school_last_id'), ['show_if_true' => 'has_been_in_school', 'key_as' => 'school_last', 'search_by' => 'name', 'source' => route('api.school.search'), 'key' => 'id', 'label' => 'name', 'list_key' => 'results'])
				->field('school_last_name', 'model_field', trans('form_builder.pesquisa.field.school_last_name'), ['show_if_true' => 'has_been_in_school', 'key' => 'school_last', 'field' => 'name'])
				->field('school_last_status', 'select', trans('form_builder.pesquisa.field.school_last_status'), ['show_if_true' => 'has_been_in_school'], ['options' => SchoolLastStatus::getAllAsArray(), 'key' => 'slug', 'label' => 'label'])
				->field('school_last_age', 'number', trans('form_builder.pesquisa.field.school_last_age'), ['show_if_true' => 'has_been_in_school'])
				->field('school_last_address', 'string', trans('form_builder.pesquisa.field.school_last_address'), ['show_if_true' => 'has_been_in_school']);
			})

			->group('work', trans('form_builder.pesquisa.group.work'), function(FormBuilder $group) {
				return $group
				->field('is_working', 'boolean', trans('form_builder.pesquisa.field.is_working'))
				->field('work_activity', 'select', trans('form_builder.pesquisa.field.work_activity'), ['show_if_true' => 'is_working', 'options' => WorkActivity::getAllAsArray(), 'key' => 'slug', 'label' => 'label'])
				->field('work_activity_other', 'string', trans('form_builder.pesquisa.field.work_activity_other'), ['show_if_equal' => ['work_activity', 'other']])
				->field('work_is_paid', 'boolean', trans('form_builder.pesquisa.field.work_is_paid'), ['show_if_true' => 'is_working'])
				->field('work_weekly_hours', 'number', trans('form_builder.pesquisa.field.work_weekly_hours'), ['show_if_true' => 'is_working']);
			})

			->group('guardians', trans('form_builder.pesquisa.group.guardians'), function (FormBuilder $group) {
				return $group
				->field('parents_has_mother', 'boolean', trans('form_builder.pesquisa.field.parents_has_mother'))
				->field('parents_has_father', 'boolean', trans('form_builder.pesquisa.field.parents_has_father'))
				->field('parents_has_brother', 'boolean', trans('form_builder.pesquisa.field.parents_has_brother'))

				->field('parents_who_is_guardian', 'select', trans('form_builder.pesquisa.field.parents_who_is_guardian'), ['options' => GuardianType::getAllAsArray(), 'key' => 'slug', 'label' => 'label'])
				->field('parents_income', 'select', trans('form_builder.pesquisa.field.parents_income'), ['options' => IncomeRange::getAllAsArray(), 'key' => 'slug', 'label' => 'label'])
				->field('mother_name', 'string', trans('form_builder.pesquisa.field.mother_name'))

				->field('guardian_name', 'string', trans('form_builder.pesquisa.field.guardian_name'))
				->field('guardian_rg', 'string', trans('form_builder.pesquisa.field.guardian_rg'))
				->field('guardian_cpf', 'string', trans('form_builder.pesquisa.field.guardian_cpf'))
				->field('guardian_dob', 'date', trans('form_builder.pesquisa.field.guardian_dob'))
				->field('guardian_phone', 'string', trans('form_builder.pesquisa.field.guardian_phone'))
				->field('guardian_race', 'select', trans('form_builder.pesquisa.field.guardian_race'), ['options' => Race::getAllAsArray(), 'key' => 'slug', 'label' => 'label'])
				->field('guardian_schooling', 'string', trans('form_builder.pesquisa.field.guardian_schooling'), ['options' => SchoolingLevel::getAllAsArray(), 'key' => 'slug', 'label' => 'label'])
				->field('guardian_job', 'string', trans('form_builder.pesquisa.field.guardian_job'));
			})

			->group('cause', trans('form_builder.pesquisa.group.cause'), function (FormBuilder $group) {
				return $group
				->field('case_cause_ids', 'multiple', trans('form_builder.pesquisa.field.case_cause_ids'), ['options' => CaseCause::getAllAsArray(), 'key' => 'id', 'label' => 'label'])
				->field('handicapped_at_sus', 'boolean', trans('form_builder.pesquisa.field.handicapped_at_sus'), ['show_if_in' => ['case_cause_ids', CaseCause::getAllHandicappedIDs()]])
				->field('handicapped_reason_not_enrolled', 'select', trans('form_builder.pesquisa.field.handicapped_reason_not_enrolled'), ['show_if_true' => 'handicapped_at_sus', 'options' => HandicappedRejectReason::getAllAsArray(), 'key' => 'slug', 'label' => 'label']);
			})

			->group('place', trans('form_builder.pesquisa.group.place'), function (FormBuilder $group) {
				return $group
				->field('place_address', 'string', trans('form_builder.pesquisa.field.place_address'))
				->field('place_cep', 'string', trans('form_builder.pesquisa.field.place_cep'))
				->field('place_reference', 'string', trans('form_builder.pesquisa.field.place_reference'))
				->field('place_neighborhood', 'string', trans('form_builder.pesquisa.field.place_neighborhood'))
				->field('place_uf', 'select', trans('form_builder.pesquisa.field.place_uf'), ['options' => UF::getAllAsArray(), 'key' => 'code', 'label' => 'name'])
				->field('place_city_id', 'model', trans('form_builder.pesquisa.field.place_city_id'), ['key_as' => 'place_city', 'search_by' => 'name', 'source' => route('api.cities.search'), 'list_key' => 'results', 'key' => 'id', 'label' => 'full_name'])
				->field('place_city_name', 'model_field', trans('form_builder.pesquisa.field.place_city_name'), ['key' => 'place_city', 'field' => 'name'])
				->field('place_kind', 'select', trans('form_builder.pesquisa.field.place_kind'), ['options' => PlaceKind::getAllAsArray(), 'key' => 'slug', 'label' => 'label'])
				->field('place_is_quilombola', 'boolean', trans('form_builder.pesquisa.field.place_is_quilombola'));
			});
	}
}