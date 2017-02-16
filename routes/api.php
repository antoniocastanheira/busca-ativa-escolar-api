<?php
Route::get('/versions', function() {
	return response()->json(['available_versions' => ['v1']]);
});

Route::group(['prefix' => 'auth'], function () {
	Route::post('/token', 'Auth\IdentityController@authenticate');
	//Route::get('/identity', 'Auth\IdentityController@identity')->middleware('jwt.auth');
});

Route::group(['prefix' => 'v1', 'middleware' => 'api'], function () {

	Route::group(['middleware' => 'jwt.auth'], function() { // Authenticated routes

		// Children
		Route::resource('/children', 'Resources\ChildrenController');
		Route::post('/children/search', 'Resources\ChildrenController@search')->middleware('can:cases.view');
		Route::get('/children/{child}/comments', 'Resources\ChildrenController@comments')->middleware('can:cases.view');
		Route::get('/children/{child}/attachments', 'Resources\ChildrenController@attachments')->middleware('can:cases.view');
		Route::get('/children/{child}/activity', 'Resources\ChildrenController@activity_log')->middleware('can:cases.view');
		Route::post('/children/{child}/comments', 'Resources\ChildrenController@addComment')->middleware('can:cases.view');
		Route::post('/children/{child}/attachments', 'Resources\ChildrenController@addAttachment')->middleware('can:cases.view');

		// Pending alerts
		Route::get('/alerts/pending', 'Resources\AlertsController@get_pending')->middleware('can:alerts.pending');
		Route::post('/alerts/{child}/accept', 'Resources\AlertsController@accept')->middleware('can:alerts.pending');
		Route::post('/alerts/{child}/reject', 'Resources\AlertsController@reject')->middleware('can:alerts.pending');

		// Child Cases
		Route::group(['middleware' => 'can:cases.view'], function() {
			Route::resource('/cases', 'Resources\CasesController');
		});

		// Users
		Route::post('/users/search', 'Resources\UsersController@search')->middleware('can:users.view');
		Route::group(['middleware' => 'can:users.manage'], function() {
			Route::post('/users/{user_id}/restore', 'Resources\UsersController@restore');
			Route::resource('/users', 'Resources\UsersController');
		});

		// Settings
		Route::group(['middleware' => 'can:settings.manage'], function() {

			// User Groups
			Route::resource('/groups', 'Resources\GroupsController');
			Route::put('/groups/{group}/settings', 'Resources\GroupsController@update_settings');

			// Tenant Settings
			Route::get('/settings/tenant', 'Resources\SettingsController@get_tenant_settings')->middleware('can:settings.manage');
			Route::put('/settings/tenant', 'Resources\SettingsController@update_tenant_settings')->middleware('can:settings.manage');

		});

		// Case Steps
		Route::post('/steps/{step_type}/{step_id}/complete', 'Resources\StepsController@complete')->middleware('can:cases.manage');
		Route::get('/steps/{step_type}/{step_id}/assignable_users', 'Resources\StepsController@getAssignableUsers')->middleware('can:cases.manage');
		Route::post('/steps/{step_type}/{step_id}/assign_user', 'Resources\StepsController@assignUser')->middleware('can:cases.manage');
		Route::post('/steps/{step_type}/{step_id}', 'Resources\StepsController@update')->middleware('can:cases.manage');
		Route::get('/steps/{step_type}/{step_id}', 'Resources\StepsController@show')->middleware('can:cases.view');

		// Tenants (authenticated)
		Route::get('/tenants/all', 'Tenants\TenantsController@all')->middleware('can:tenants.manage');

		// INEP Schools
		Route::post('/schools/search', 'Resources\SchoolsController@search');

		// Reports
		Route::post('/reports/children', 'Resources\ReportsController@query_children')->middleware('can:reports.view');

	});

	// Attachment download
	// TODO: IMPORTANT: authenticate this with special timed download token
	Route::get('/attachments/download/{attachment}', 'Resources\AttachmentsController@download')->name('api.attachments.download');

	// Static data
	Route::get('/language.json', 'Resources\LanguageController@generateLanguageFile');
	Route::get('/static/static_data', 'Resources\StaticDataController@render');

	// Open data for sign-up
	Route::post('/cities/search', 'Resources\CitiesController@search');
	Route::resource('/cities', 'Resources\CitiesController');
	Route::resource('/tenants', 'Tenants\TenantsController');

});
