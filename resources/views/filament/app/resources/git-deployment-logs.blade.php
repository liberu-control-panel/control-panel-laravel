<div class="space-y-4">
    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
        <h3 class="text-lg font-semibold mb-2">Deployment Log</h3>
        @if($log)
            <pre class="bg-gray-800 text-green-400 p-4 rounded overflow-x-auto text-sm font-mono whitespace-pre-wrap">{{ $log }}</pre>
        @else
            <p class="text-gray-500 dark:text-gray-400">No logs available yet.</p>
        @endif
    </div>
</div>
