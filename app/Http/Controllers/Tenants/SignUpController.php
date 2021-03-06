<?php
/**
 * busca-ativa-escolar-api
 * TenantSignUpController.php
 *
 * Copyright (c) LQDI Digital
 * www.lqdi.net - 2016
 *
 * @author Aryel Tupinambá <aryel.tupinamba@lqdi.net>
 *
 * Created at: 22/12/2016, 21:09
 */

namespace BuscaAtivaEscolar\Http\Controllers\Tenants;


use Auth;
use BuscaAtivaEscolar\City;
use BuscaAtivaEscolar\Exceptions\ValidationException;
use BuscaAtivaEscolar\Http\Controllers\BaseController;
use BuscaAtivaEscolar\SignUp;
use BuscaAtivaEscolar\Tenant;
use BuscaAtivaEscolar\User;
use DB;
use Event;

class SignUpController extends BaseController  {

	public function register() {
		$data = request()->all();

		if(!isset($data['city_id'])) return $this->api_failure('missing_city_id');

		$city = City::find($data['city_id']);

		if(!$city) return $this->api_failure('invalid_city');

		$existingTenant = Tenant::where('city_id', $city->id)->first();
		$existingSignUp = SignUp::where('city_id', $city->id)->first();

		if($existingTenant) return $this->api_failure('tenant_already_registered');
		if($existingSignUp) return $this->api_failure('signup_in_progress');

		try {

			$validator = SignUp::validate($data);

			if($validator->fails()) {
				return $this->api_failure('invalid_input', $validator->failed());
			}

			if(User::checkIfExists($data['admin']['email'])) {
				return $this->api_failure('political_admin_email_in_use');
			}

			$signup = SignUp::createFromForm($data);

			return response()->json(['status' => 'ok', 'signup_id' => $signup->id]);
		} catch (\Exception $ex) {
			return $this->api_exception($ex);
		}

	}

	public function get_pending() {
		$pending = SignUp::with('city')
			->where('is_provisioned', false);

		SignUp::applySorting($pending, request('sort', []));
		
		$pending = $pending->get();
		
		return response()->json(['data' => $pending]);
	}

	public function get_via_token(SignUp $signup) {
		$token = request('token');
		$validToken = $signup->getURLToken();

		if(!$token) return $this->api_failure('invalid_token');
		if($token !== $validToken) return $this->api_failure('token_mismatch');
		if(!$signup->is_approved) return $this->api_failure('not_approved');
		if($signup->is_provisioned) return $this->api_failure('already_provisioned');

		return response()->json($signup);
	}

	public function approve(SignUp $signup) {
		try {

			if(!$signup) return $this->api_failure('invalid_signup_id');

			$signup->approve(Auth::user());
			return response()->json(['status' => 'ok', 'signup_id' => $signup->id]);

		} catch (\Exception $ex) {
			return $this->api_exception($ex);
		}
	}

	public function reject(SignUp $signup) {
		try {

			if(!$signup) return $this->api_failure('invalid_signup_id');

			$signup->reject(Auth::user());
			return response()->json(['status' => 'ok', 'signup_id' => $signup->id]);

		} catch (\Exception $ex) {
			return $this->api_exception($ex);
		}
	}

	public function resendNotification(SignUp $signup) {
		try {

			if(!$signup) return $this->api_failure('invalid_signup_id');

			$signup->sendNotification();
			return response()->json(['status' => 'ok', 'signup_id' => $signup->id]);

		} catch (\Exception $ex) {
			return $this->api_exception($ex);
		}
	}

	public function complete(SignUp $signup) {
		$token = request('token');
		$validToken = $signup->getURLToken();

		if(!$token) return $this->api_failure('invalid_token');
		if($token !== $validToken) return $this->api_failure('token_mismatch');
		if(!$signup->is_approved) return $this->api_failure('not_approved');
		if($signup->is_provisioned) return $this->api_failure('already_provisioned');

		$politicalAdmin = request('political', []);
		$operationalAdmin = request('operational', []);

		try {
			$tenant = Tenant::provision($signup, $politicalAdmin, $operationalAdmin);

			return response()->json(['status' => 'ok', 'tenant_id' => $tenant->id]);
		} catch (ValidationException $ex) {
			if($ex->getValidator()) return $this->api_validation_failed($ex->getReason(), $ex->getValidator());
			return $this->api_failure($ex->getReason());
		} catch (\Exception $ex) {
			return $this->api_exception($ex);
		}
	}

	public function completeSetup() {

		$tenant = Auth::user()->tenant;

		if(!$tenant) return $this->api_failure('user_has_no_tenant');

		$tenant->is_setup = true;
		$tenant->save();

		return response()->json(['status' => 'ok']);

	}

}