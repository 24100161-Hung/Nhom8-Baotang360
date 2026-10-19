{{-- Chân trang học thuật bắt buộc, render phía máy chủ (luôn có trong View source). Dùng cho app.blade.php và errors/*. --}}
<footer class="bt360-academic-footer {{ $class ?? '' }}" role="contentinfo" data-academic-footer>
    <p><strong>CSE703073 – {{ __('footer.course_name') }}</strong> · {{ __('footer.team_full') }} · {{ __('footer.school') }}</p>
    <p>{{ __('footer.disclaimer') }}</p>
    <p>{{ __('footer.version_label') }} {{ config('bt360.version') }}</p>
</footer>
