<?php
/**
 * 설정 페이지
 * 
 * @package WP_Smart_Bridge
 * @since 2.5.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// 현재 사용자의 API 키 목록
$user_id = get_current_user_id();
$api_keys = SB_Database::get_user_api_keys($user_id);
$settings = get_option('sb_settings', []);

$redirect_delay = isset($settings['redirect_delay']) ? $settings['redirect_delay'] : 0;
$default_loading_message = isset($settings['default_loading_message']) ? $settings['default_loading_message'] : '잠시만 기다려주세요...';
?>

<div class="wrap sb-settings">
    <h1>
        <span class="dashicons dashicons-admin-generic"></span>
        Smart Bridge 설정
    </h1>

    <!-- API 키 관리 -->
    <div class="sb-settings-section">
        <h2>🔑 API 키 관리</h2>
        <p class="description">
            EXE 프로그램에서 사용할 API 키를 관리합니다.
            <strong>Secret Key는 절대 외부에 노출하지 마세요.</strong>
        </p>

        <div class="sb-api-keys-actions">
            <button type="button" id="sb-generate-key" class="button button-primary">
                <span class="dashicons dashicons-plus-alt2"></span>
                새 API 키 발급
            </button>
        </div>

        <table class="wp-list-table widefat fixed striped sb-api-keys-table">
            <thead>
                <tr>
                    <th style="width: 25%;">API Key (공개 키)</th>
                    <th style="width: 30%;">Secret Key (비밀 키)</th>
                    <th style="width: 15%;">상태</th>
                    <th style="width: 15%;">마지막 사용</th>
                    <th style="width: 15%;">액션</th>
                </tr>
            </thead>
            <tbody id="sb-api-keys-list">
                <?php if (empty($api_keys)): ?>
                    <tr class="sb-no-keys">
                        <td colspan="5" class="sb-no-data">
                            발급된 API 키가 없습니다. 위의 버튼을 클릭하여 새 키를 발급하세요.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($api_keys as $key): ?>
                        <tr data-key-id="<?php echo esc_attr($key['id']); ?>">
                            <td>
                                <code class="sb-api-key"><?php echo esc_html($key['api_key']); ?></code>
                                <button type="button" class="button button-small sb-copy-btn"
                                    data-copy="<?php echo esc_attr($key['api_key']); ?>">
                                    📋
                                </button>
                            </td>
                            <td>
                                <code class="sb-secret-key sb-masked">••••••••••••••••</code>
                                <code class="sb-secret-key sb-revealed" style="display: none;">
                                                                                                    <?php echo esc_html($key['secret_key']); ?>
                                                                                                </code>
                                <button type="button" class="button button-small sb-toggle-secret">
                                    👁️
                                </button>
                                <button type="button" class="button button-small sb-copy-btn"
                                    data-copy="<?php echo esc_attr($key['secret_key']); ?>">
                                    📋
                                </button>
                            </td>
                            <td>
                                <span class="sb-status sb-status-<?php echo esc_attr($key['status']); ?>">
                                    <?php echo $key['status'] === 'active' ? '✅ 활성' : '❌ 비활성'; ?>
                                </span>
                            </td>
                            <td>
                                <?php
                                if ($key['last_used_at']) {
                                    echo esc_html(date('Y-m-d H:i', strtotime($key['last_used_at'])));
                                } else {
                                    echo '<span class="sb-muted">사용 기록 없음</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <button type="button" class="button button-small button-link-delete sb-delete-key"
                                    data-key-id="<?php echo esc_attr($key['id']); ?>">
                                    삭제
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- 일반 설정 -->
    <div class="sb-settings-section">
        <h2>⚙️ 일반 설정</h2>

        <form id="sb-settings-form">
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="sb-redirect-delay">리다이렉션 딜레이</label>
                    </th>
                    <td>
                        <input type="number" id="sb-redirect-delay" name="redirect_delay"
                            value="<?php echo esc_attr($redirect_delay); ?>" min="0" max="10" step="0.1"
                            style="width: 100px;" />
                        <span style="margin-left: 5px;">초</span>
                        <p class="description">
                            로딩 메시지를 표시할 시간입니다. 0초면 바로 리다이렉션됩니다.<br>
                            <strong>0.5초, 1.5초</strong> 같은 소수점 단위도 입력 가능합니다.
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">
                        <label for="sb-default-loading-message">기본 로딩 메시지</label>
                    </th>
                    <td>
                        <textarea id="sb-default-loading-message" name="default_loading_message" rows="3"
                            class="large-text"><?php echo esc_textarea($default_loading_message); ?></textarea>
                        <p class="description">
                            리다이렉션 딜레이가 설정된 경우 표시될 기본 메시지입니다.
                            허용 태그: &lt;strong&gt;, &lt;em&gt;, &lt;br&gt;, &lt;p&gt;, &lt;span&gt;
                        </p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    설정 저장
                </button>
            </p>
        </form>
    </div>

    <!-- 커스텀 리다이렉션 템플릿 -->
    <div class="sb-settings-section">
        <h2>🎨 커스텀 리다이렉션 템플릿</h2>
        <p class="description">
            리다이렉션 대기 페이지의 전체 HTML/CSS를 자유롭게 커스터마이징할 수 있습니다.<br>
            <strong>⚠️ 필수 Placeholder를 반드시 포함해야 합니다!</strong>
        </p>

        <div
            style="margin: 20px 0; padding: 15px; background: #e7f3ff; border-left: 4px solid #2196F3; border-radius: 4px;">
            <h4 style="margin: 0 0 10px;">📝 필수 Placeholder 목록</h4>
            <ul style="margin: 0; padding-left: 20px;">
                <li><code>{{DELAY_SECONDS}}</code> - 초기 딜레이 초가 표시될 위치</li>
                <li><code>{{TARGET_URL}}</code> - 타겟 URL (href 속성 등에 사용)</li>
                <li><code>{{COUNTDOWN_SCRIPT}}</code> - 카운트다운 JavaScript 코드</li>
                <li><code>id="countdown"</code> - 카운트다운 숫자가 업데이트될 요소의 ID (반드시 필요)</li>
            </ul>
            <p style="margin: 10px 0 0; font-size: 12px; color: #666;">
                💡 <strong>로딩 메시지</strong>는 placeholder 없이 HTML에 직접 입력하세요!
            </p>
        </div>

        <div
            style="margin: 20px 0; padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
            <h4 style="margin: 0 0 10px;">🤖 AI로 디자인 변경하기</h4>
            <p style="margin: 0 0 10px; font-size: 13px;">
                ChatGPT, Claude 등 AI에게 아래 프롬프트를 복사해서 붙여넣으면 안전하게 디자인을 변경할 수 있습니다:
            </p>
            <textarea readonly
                style="width: 100%; height: 180px; font-family: monospace; font-size: 12px; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"><?php echo esc_textarea(SB_Helpers::get_ai_prompt_example()); ?></textarea>
            <p style="margin: 10px 0 0; font-size: 12px; color: #666;">
                💡 <strong>사용 방법</strong>: 위 프롬프트를 복사 → 아래 "현재 템플릿" 복사해서 AI에게 함께 전달 → AI가 생성한 HTML을 아래 편집기에 붙여넣기
            </p>
        </div>

        <form id="sb-template-form">
            <div style="margin-bottom: 15px;">
                <label for="sb-redirect-template" style="font-weight: 600; display: block; margin-bottom: 5px;">
                    리다이렉션 페이지 HTML 템플릿
                </label>
                <textarea id="sb-redirect-template" name="redirect_template" rows="20"
                    style="width: 100%; font-family: 'Courier New', monospace; font-size: 12px; padding: 10px;"><?php
                    $current_template = get_option('sb_redirect_template', SB_Helpers::get_default_redirect_template());
                    echo esc_textarea($current_template);
                    ?></textarea>
                <p class="description" style="margin-top: 5px;">
                    전체 HTML을 자유롭게 편집할 수 있습니다. CSS, JavaScript 포함 가능합니다.
                </p>
            </div>

            <div id="sb-template-validation"
                style="display: none; padding: 15px; margin-bottom: 15px; border-radius: 4px;"></div>

            <p class="submit" style="display: flex; gap: 10px;">
                <button type="button" id="sb-validate-template" class="button">
                    ✓ 템플릿 검증
                </button>
                <button type="submit" class="button button-primary" id="sb-save-template">
                    템플릿 저장
                </button>
                <button type="button" id="sb-reset-template" class="button">
                    기본값으로 복원
                </button>
            </p>
        </form>

        <script>
            jQuery(document).ready(function ($) {
                // 템플릿 검증
                $('#sb-validate-template').on('click', function () {
                    var template = $('#sb-redirect-template').val();
                    validateTemplate(template, true);
                });

                // 템플릿 저장
                $('#sb-template-form').on('submit', function (e) {
                    e.preventDefault();

                    var template = $('#sb-redirect-template').val();
                    var validation = validateTemplate(template, false);

                    if (!validation.valid) {
                        return;
                    }

                    var $btn = $('#sb-save-template');
                    $btn.prop('disabled', true).text('저장 중...');

                    $.ajax({
                        url: sbAdmin.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'sb_save_redirect_template',
                            nonce: sbAdmin.ajaxNonce,
                            template: template
                        },
                        success: function (response) {
                            if (response.success) {
                                showValidation(true, '✅ 템플릿이 저장되었습니다!');
                            } else {
                                showValidation(false, '❌ ' + (response.data.message || '저장 실패'));
                            }
                        },
                        error: function () {
                            showValidation(false, '❌ 통신 오류가 발생했습니다.');
                        },
                        complete: function () {
                            $btn.prop('disabled', false).text('템플릿 저장');
                        }
                    });
                });

                // 기본값 복원
                $('#sb-reset-template').on('click', function () {
                    if (!confirm('정말 기본 템플릿으로 복원하시겠습니까? 현재 템플릿은 사라집니다.')) {
                        return;
                    }

                    $.ajax({
                        url: sbAdmin.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'sb_reset_redirect_template',
                            nonce: sbAdmin.ajaxNonce
                        },
                        success: function (response) {
                            if (response.success && response.data.template) {
                                $('#sb-redirect-template').val(response.data.template);
                                showValidation(true, '✅ 기본 템플릿으로 복원되었습니다!');
                            }
                        }
                    });
                });

                function validateTemplate(template, showSuccess) {
                    var required = [
                        '{{LOADING_MESSAGE}}',
                        '{{DELAY_SECONDS}}',
                        '{{TARGET_URL}}',
                        '{{COUNTDOWN_SCRIPT}}',
                        'id="countdown"'
                    ];

                    var missing = [];
                    required.forEach(function (placeholder) {
                        if (template.indexOf(placeholder) === -1) {
                            missing.push(placeholder);
                        }
                    });

                    var valid = missing.length === 0;

                    if (showSuccess || !valid) {
                        showValidation(valid, valid
                            ? '✅ 모든 필수 Placeholder가 포함되어 있습니다!'
                            : '❌ 누락된 Placeholder: ' + missing.join(', ')
                        );
                    }

                    return { valid: valid, missing: missing };
                }

                function showValidation(isValid, message) {
                    var $box = $('#sb-template-validation');
                    $box.show()
                        .css({
                            'background': isValid ? '#d1f2dd' : '#f8d7da',
                            'border': '1px solid ' + (isValid ? '#00a32a' : '#d63638'),
                            'color': isValid ? '#00664a' : '#721c24'
                        })
                        .html('<strong>' + message + '</strong>');

                    setTimeout(function () {
                        if (isValid) {
                            $box.fadeOut();
                        }
                    }, 5000);
                }
            });
        </script>
    </div>

    <!-- 사용 안내 -->
    <div class="sb-settings-section sb-usage-guide">
        <h2>📖 EXE 프로그램 연동 방법</h2>

        <div class="sb-guide-content">
            <h4>1. API 키 발급</h4>
            <p>위의 "새 API 키 발급" 버튼을 클릭하여 API Key와 Secret Key를 발급받습니다.</p>

            <h4>2. EXE 프로그램 설정</h4>
            <p>EXE 프로그램의 설정에서 다음 정보를 입력합니다:</p>
            <ul>
                <li><strong>Base URL:</strong> <code><?php echo home_url(); ?></code></li>
                <li><strong>API Key:</strong> 발급받은 공개 키 (sb_live_xxx)</li>
                <li><strong>Secret Key:</strong> 발급받은 비밀 키 (sk_secret_xxx)</li>
            </ul>

            <h4>3. 링크 생성</h4>
            <p>EXE 프로그램에서 제휴 링크가 생성될 때 자동으로 단축 링크로 생성됩니다.</p>

            <div class="sb-warning-box">
                <strong>⚠️ 주의사항</strong>
                <p>워드프레스 관리자 페이지에서는 링크를 생성할 수 없습니다.
                    반드시 EXE 프로그램을 사용해야 합니다.</p>
            </div>
        </div>
    </div>
</div>

<!-- 새 키 발급 모달 -->
<div id="sb-new-key-modal" class="sb-modal" style="display: none;">
    <div class="sb-modal-content">
        <h3>🎉 새 API 키가 발급되었습니다!</h3>
        <p><strong>아래 정보를 안전한 곳에 저장하세요. Secret Key는 다시 확인할 수 없습니다.</strong></p>

        <div class="sb-key-display">
            <label>API Key (공개 키)</label>
            <div class="sb-key-row">
                <code id="sb-new-api-key"></code>
                <button type="button" class="button sb-copy-modal-key" data-target="sb-new-api-key">복사</button>
            </div>
        </div>

        <div class="sb-key-display">
            <label>Secret Key (비밀 키) - ⚠️ 다시 확인 불가!</label>
            <div class="sb-key-row">
                <code id="sb-new-secret-key"></code>
                <button type="button" class="button sb-copy-modal-key" data-target="sb-new-secret-key">복사</button>
            </div>
        </div>

        <div class="sb-modal-actions">
            <button type="button" class="button button-primary sb-close-modal">확인</button>
        </div>
    </div>
</div>