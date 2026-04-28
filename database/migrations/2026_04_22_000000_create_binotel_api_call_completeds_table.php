<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('binotel_api_call_completeds', function (Blueprint $table) {
            $table->id();
            $table->string('request_type')->nullable();
            $table->unsignedInteger('attempts_counter')->nullable();
            $table->string('language')->nullable();
            $table->string('my_binotel_domain')->nullable();

            $table->string('call_details_company_id')->nullable();
            $table->string('call_details_general_call_id')->nullable()->unique();
            $table->string('call_details_call_id')->nullable()->index();
            $table->unsignedBigInteger('call_details_start_time')->nullable();
            $table->string('call_details_call_type')->nullable();
            $table->string('call_details_internal_number')->nullable();
            $table->string('call_details_internal_additional_data')->nullable();
            $table->string('call_details_external_number')->nullable()->index();
            $table->unsignedInteger('call_details_waitsec')->nullable();
            $table->unsignedInteger('call_details_billsec')->nullable();
            $table->string('call_details_disposition')->nullable()->index();
            $table->string('call_details_recording_status')->nullable()->index();
            $table->boolean('call_details_is_new_call')->nullable();
            $table->string('call_details_who_hung_up')->nullable();
            $table->json('call_details_customer_data')->nullable();

            $table->string('call_details_employee_name')->nullable();
            $table->string('call_details_employee_email')->nullable();

            $table->string('call_details_pbx_number')->nullable();
            $table->string('call_details_pbx_name')->nullable();

            $table->string('call_details_customer_from_outside_id')->nullable();
            $table->string('call_details_customer_from_outside_external_number')->nullable();
            $table->string('call_details_customer_from_outside_name')->nullable();
            $table->text('call_details_customer_from_outside_link_to_crm_url')->nullable();

            $table->string('call_details_call_tracking_id')->nullable();
            $table->string('call_details_call_tracking_type')->nullable();
            $table->string('call_details_call_tracking_ga_client_id')->nullable();
            $table->unsignedBigInteger('call_details_call_tracking_first_visit_at')->nullable();
            $table->text('call_details_call_tracking_full_url')->nullable();
            $table->string('call_details_call_tracking_utm_source')->nullable();
            $table->string('call_details_call_tracking_utm_medium')->nullable();
            $table->string('call_details_call_tracking_utm_campaign')->nullable();
            $table->string('call_details_call_tracking_utm_content')->nullable();
            $table->string('call_details_call_tracking_utm_term')->nullable();
            $table->string('call_details_call_tracking_ip_address')->nullable();
            $table->string('call_details_call_tracking_geoip_country')->nullable();
            $table->string('call_details_call_tracking_geoip_region')->nullable();
            $table->string('call_details_call_tracking_geoip_city')->nullable();
            $table->string('call_details_call_tracking_geoip_org')->nullable();
            $table->string('call_details_call_tracking_domain')->nullable();
            $table->string('call_details_call_tracking_ga_tracking_id')->nullable();
            $table->unsignedInteger('call_details_call_tracking_time_spent_on_site_before_make_call')->nullable();

            $table->text('call_details_link_to_call_record_overlay_in_my_business')->nullable();
            $table->text('call_details_link_to_call_record_in_my_business')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('binotel_api_call_completeds');
    }
};
