@extends('layouts.app')

@section('content')
    <div class="min-h-full flex flex-col sm:justify-center items-center pt-6 sm:pt-0">

        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
            <x-validation-errors class="mb-4" />
        
            <form method="POST" action="{{ route('register') }}">
                @csrf

                <div>
                    <x-label for="name" value="{{ __('Name') }}" />
                    <x-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                    <x-input-error for="name" class="mt-2" />
                </div>

                <div class="mt-4">
                    <x-label for="email" value="{{ __('Email') }}" />
                    <x-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required />
                    <x-input-error for="email" class="mt-2" />
                </div>

                <div class="mt-4">
                    <x-label for="password" value="{{ __('Password') }}" />
                    <x-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
                    <x-input-error for="password" class="mt-2" />
                </div>

                <div class="mt-4">
                    <x-label for="password_confirmation" value="{{ __('Confirm Password') }}" />
                    <x-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required />
                    <x-input-error for="password_confirmation" class="mt-2" />
                </div>

                <div class="mt-4">
                    <x-label for="role" value="{{ __('Role') }}" />
                    <select id="role" name="role" class="block mt-1 w-full border-gray-300 focus:border-blue-500 focus:ring-blue-500 rounded-md shadow-sm transition-colors duration-150" required>
                        <option value="">{{ __('Select your role') }}</option>
                        <option value="tenant">{{ __('Tenant - I am looking to rent a property') }}</option>
                        <option value="buyer">{{ __('Buyer - I am looking to purchase a property') }}</option>
                        <option value="seller">{{ __('Seller - I want to sell my property') }}</option>
                        <option value="landlord">{{ __('Landlord - I want to rent out my property') }}</option>
                        <option value="contractor">{{ __('Contractor - I provide maintenance/construction services') }}</option>
                    </select>
                    <x-input-error for="role" class="mt-2" />
                </div>

                <div class="flex items-center justify-end mt-4">
                    <a class="underline text-sm text-gray-600 hover:text-gray-900" href="{{ route('login') }}">
                        {{ __('Already registered?') }}
                    </a>

                    <x-button class="ml-4">
                        {{ __('Register') }}
                    </x-button>
                </div>
            </form>
        </div>
    </div>
@endsection
