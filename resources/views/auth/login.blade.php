@extends('layouts.app')

@section('content')
    <div class="min-h-full flex flex-col sm:justify-center items-center pt-6 sm:pt-0">
        <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
            <div class="mb-4 text-sm text-gray-600">
                {{ __('Please sign in to access the admin panel.') }}
            </div>
        
            <x-validation-errors class="mb-4" />
        
            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div>
                    <label class="block font-medium text-sm text-gray-700" for="email">
                        {{ __('Email') }}
                    </label>
                    <input class="border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 rounded-md shadow-sm block mt-1 w-full transition-colors duration-150 @error('email') border-red-500 focus:border-red-500 focus:ring-red-500 @enderror" id="email" type="email" name="email" value="{{ old('email') }}" required="required" autofocus="autofocus">
                    @error('email')
                        <p class="text-sm text-red-600 mt-1 flex items-start">
                            <svg class="h-4 w-4 text-red-500 mr-1 mt-0.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                <div class="mt-4">
                    <label class="block font-medium text-sm text-gray-700" for="password">
                        {{ __('Password') }}
                    </label>
                    <input class="border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 rounded-md shadow-sm block mt-1 w-full transition-colors duration-150 @error('password') border-red-500 focus:border-red-500 focus:ring-red-500 @enderror" id="password" type="password" name="password" required="required" autocomplete="current-password">
                    @error('password')
                        <p class="text-sm text-red-600 mt-1 flex items-start">
                            <svg class="h-4 w-4 text-red-500 mr-1 mt-0.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-8-5a.75.75 0 01.75.75v4.5a.75.75 0 01-1.5 0v-4.5A.75.75 0 0110 5zm0 10a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                            </svg>
                            <span>{{ $message }}</span>
                        </p>
                    @enderror
                </div>

                <div class="block mt-4">
                    <label for="remember_me" class="flex items-center cursor-pointer">
                        <input type="checkbox" class="rounded border-gray-300 text-blue-600 shadow-sm focus:border-blue-500 focus:ring focus:ring-blue-500 focus:ring-opacity-50 transition-colors duration-150" id="remember_me" name="remember">
                        <span class="ml-2 text-sm text-gray-600">{{ __('Remember me') }}</span>
                    </label>
                </div>

                <div class="flex items-center justify-end mt-4">
                    <button type="submit" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 focus:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition-all ease-in-out duration-150 ml-4">
                        {{ __('Log in') }}
                    </button>                    
                </div>

                <a href="/forgot-password" class="underline text-sm text-gray-600 hover:text-gray-900" >Forgot password?</a>
            </form>
        </div>
    </div>
@endsection
