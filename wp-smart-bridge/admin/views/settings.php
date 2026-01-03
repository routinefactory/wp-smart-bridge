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
                        <select id="sb-redirect-delay" name="redirect_delay">
                            <option value="0" <?php selected($redirect_delay, 0); ?>>즉시 리다이렉션 (0초)</option>
                            <option value="1" <?php selected($redirect_delay, 1); ?>>1초</option>
                            <option value="2" <?php selected($redirect_delay, 2); ?>>2초</option>
                            <option value="3" <?php selected($redirect_delay, 3); ?>>3초</option>
                            <option value="5" <?php selected($redirect_delay, 5); ?>>5초</option>
                        </select>
                        <p class="description">
                            로딩 메시지를 표시할 시간입니다. 0초면 바로 리다이렉션됩니다.
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
            <p>EXE 프로그램에서 제휴 링크를 입력하면 자동으로 단축 링크가 생성됩니다.</p>

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