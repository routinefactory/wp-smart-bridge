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

// -------------------------------------------------------------------------
// View Logic Moved to DB_Admin::render_settings()
// -------------------------------------------------------------------------

// Data is passed from Controller:
// $api_keys, $settings, $redirect_delay
?>

<div class="wrap sb-settings">
    <h1>
        <span class="dashicons dashicons-admin-generic"></span>
        <?php _e('Smart Bridge 설정', 'sb'); ?>
    </h1>

    <!-- 탭 네비게이션 -->
    <div class="sb-settings-tabs-nav">
        <button class="sb-tab-btn active" data-tab="api-keys">
            <span class="dashicons dashicons-lock"></span>
            <?php _e('API 키 관리', 'sb'); ?>
        </button>
        <button class="sb-tab-btn" data-tab="data-optimization">
            <span class="dashicons dashicons-performance"></span>
            <?php _e('데이터 최적화', 'sb'); ?>
        </button>
        <button class="sb-tab-btn" data-tab="general-settings">
            <span class="dashicons dashicons-admin-settings"></span>
            <?php _e('일반 설정', 'sb'); ?>
        </button>
        <button class="sb-tab-btn" data-tab="custom-template">
            <span class="dashicons dashicons-art"></span>
            <?php _e('커스텀 템플릿', 'sb'); ?>
        </button>
        <button class="sb-tab-btn" data-tab="backup-restore">
            <span class="dashicons dashicons-download"></span>
            <?php _e('백업/복원', 'sb'); ?>
        </button>
        <button class="sb-tab-btn" data-tab="factory-reset">
            <span class="dashicons dashicons-warning"></span>
            <?php _e('공장 초기화', 'sb'); ?>
        </button>
        <button class="sb-tab-btn" data-tab="update-rollback">
            <span class="dashicons dashicons-update"></span>
            <?php _e('업데이트 및 롤백', 'sb'); ?>
        </button>
    </div>

    <!-- 탭 컨텐츠 영역 -->
    <div class="sb-settings-tabs-content">

        <!-- 탭 1: API 키 관리 -->
        <div class="sb-tab-pane active" id="tab-api-keys">
            <div class="sb-settings-section">
                <h2><span class="dashicons dashicons-lock"></span> <?php _e('API 키 관리', 'sb'); ?></h2>
                <p class="description">
                    <?php _e('EXE 프로그램에서 사용할 API 키를 관리합니다.', 'sb'); ?>
                    <strong><?php _e('Secret Key는 절대 외부에 노출하지 마세요.', 'sb'); ?></strong>
                </p>

                <div class="sb-api-keys-actions">
                    <button type="button" id="sb-generate-key" class="button button-primary">
                        <span class="dashicons dashicons-plus-alt2"></span>
                        <?php _e('새 API 키 발급', 'sb'); ?>
                    </button>
                </div>

                <table class="wp-list-table widefat fixed striped sb-api-keys-table">
                    <thead>
                        <tr>
                            <th class="sb-settings-table-th-key"><?php _e('API Key (공개 키)', 'sb'); ?></th>
                            <th class="sb-settings-table-th-secret"><?php _e('Secret Key (비밀 키)', 'sb'); ?></th>
                            <th class="sb-settings-table-th-status"><?php _e('상태', 'sb'); ?></th>
                            <th class="sb-settings-table-th-date"><?php _e('마지막 사용', 'sb'); ?></th>
                            <th class="sb-settings-table-th-action"><?php _e('액션', 'sb'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="sb-api-keys-list">
                        <?php if (empty($api_keys)): ?>
                            <tr class="sb-no-keys">
                                <td colspan="5" class="sb-no-data">
                                    <?php _e('발급된 API 키가 없습니다. 위의 버튼을 클릭하여 새 키를 발급하세요.', 'sb'); ?>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($api_keys as $key): ?>
                                <tr data-key-id="<?php echo esc_attr($key['id']); ?>">
                                    <td>
                                        <code class="sb-api-key"><?php echo esc_html($key['api_key']); ?></code>
                                        <button type="button" class="button button-small sb-copy-btn"
                                            data-copy="<?php echo esc_attr($key['api_key']); ?>">
                                            <span class="dashicons dashicons-clipboard"></span>
                                        </button>
                                    </td>
                                    <td>
                                        <code class="sb-secret-key sb-masked">••••••••••••••••</code>
                                        <code
                                            class="sb-secret-key sb-revealed sb-hidden"><?php echo esc_html($key['secret_key']); ?></code>
                                        <button type="button" class="button button-small sb-toggle-secret">
                                            <span class="dashicons dashicons-visibility"></span>
                                        </button>
                                        <button type="button" class="button button-small sb-copy-btn"
                                            data-copy="<?php echo esc_attr($key['secret_key']); ?>">
                                            <span class="dashicons dashicons-clipboard"></span>
                                        </button>
                                    </td>
                                    <td>
                                        <span class="sb-status sb-status-<?php echo esc_attr($key['status']); ?>">
                                            <?php echo $key['status'] === 'active' ? '<span class="dashicons dashicons-yes"></span> ' . __('활성', 'sb') : '<span class="dashicons dashicons-no"></span> ' . __('비활성', 'sb'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        if ($key['last_used_at']) {
                                            echo esc_html(date('Y-m-d H:i', strtotime($key['last_used_at'])));
                                        } else {
                                            echo '<span class="sb-muted">' . __('사용 기록 없음', 'sb') . '</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <button type="button" class="button button-small button-link-delete sb-delete-key"
                                            data-key-id="<?php echo esc_attr($key['id']); ?>">
                                            <?php _e('삭제', 'sb'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 탭 2: 데이터 최적화 -->
        <div class="sb-tab-pane" id="tab-data-optimization">
            <div class="sb-settings-section">
                <h2><span class="dashicons dashicons-performance"></span> <?php _e('데이터 최적화', 'sb'); ?></h2>
                <p class="description">
                    <?php _e('대시보드 로딩 속도를 획기적으로 개선하기 위해 과거 로그 데이터를 일별 요약 테이블로 변환합니다.', 'sb'); ?><br>
                    <?php _e('데이터가 많을 경우 시간이 소요될 수 있습니다. (진행 중 페이지를 닫지 마세요)', 'sb'); ?>
                </p>

                <div class="sb-optimization-actions">
                    <button type="button" id="sb-migrate-stats" class="button button-secondary">
                        <span class="dashicons dashicons-performance"></span>
                        <?php _e('데이터 마이그레이션 시작', 'sb'); ?>
                    </button>
                    <span id="sb-migrate-status" class="sb-status-text sb-ml-10" style="display:none;"></span>
                </div>
            </div>
        </div>

        <!-- 탭 3: 일반 설정 -->
        <div class="sb-tab-pane" id="tab-general-settings">
            <div class="sb-settings-section">
                <h2><span class="dashicons dashicons-admin-settings"></span> <?php _e('일반 설정', 'sb'); ?></h2>

                <form id="sb-settings-form">
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="sb-redirect-delay"><?php _e('리다이렉션 딜레이', 'sb'); ?></label>
                            </th>
                            <td>
                                <input type="number" id="sb-redirect-delay" name="redirect_delay"
                                    value="<?php echo esc_attr($redirect_delay); ?>" min="0" max="10" step="0.1"
                                    class="sb-input-short" />
                                <span class="sb-text-unit"><?php _e('초', 'sb'); ?></span>
                                <p class="description">
                                    <?php _e('로딩 메시지를 표시할 시간입니다. 0초면 바로 리다이렉션됩니다.', 'sb'); ?><br>
                                    <strong><?php _e('0.5초, 1.5초', 'sb'); ?></strong> <?php _e('같은 소수점 단위도 입력 가능합니다.', 'sb'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php _e('설정 저장', 'sb'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <!-- 탭 4: 커스텀 템플릿 -->
        <div class="sb-tab-pane" id="tab-custom-template">
            <div class="sb-settings-section">
                <h2><span class="dashicons dashicons-art"></span> <?php _e('커스텀 리다이렉션 템플릿', 'sb'); ?></h2>
                <p class="description">
                    <?php _e('리다이렉션 대기 페이지의 전체 HTML/CSS를 자유롭게 커스터마이징할 수 있습니다.', 'sb'); ?><br>
                    <strong>⚠️ <?php _e('필수 Placeholder를 반드시 포함해야 합니다!', 'sb'); ?></strong>
                </p>

                <div class="sb-info-box sb-info-box-blue">
                    <h4><span class="dashicons dashicons-editor-ul"></span> <?php _e('필수 Placeholder 목록', 'sb'); ?></h4>
                    <ul class="sb-placeholder-list">
                        <li><code>{{DELAY_SECONDS}}</code> - <?php _e('초기 딜레이 초가 표시될 위치', 'sb'); ?></li>
                        <li><code>{{TARGET_URL}}</code> - <?php _e('타겟 URL (href 속성 등에 사용)', 'sb'); ?></li>
                        <li><code>{{COUNTDOWN_SCRIPT}}</code> - <?php _e('카운트다운 JavaScript 코드', 'sb'); ?></li>
                        <li><code>{{COUNTDOWN_ID}}</code> - <?php _e('카운트다운 요소의 ID (예: id="{{COUNTDOWN_ID}}")', 'sb'); ?></li>
                    </ul>
                    <p class="sb-helper-text">
                        💡 <strong><?php _e('로딩 메시지', 'sb'); ?></strong><?php _e('는 placeholder 없이 HTML에 직접 입력하세요!', 'sb'); ?>
                    </p>
                </div>

                <div class="sb-info-box sb-info-box-yellow">
                    <h4><span class="dashicons dashicons-superhero"></span> <?php _e('AI로 디자인 변경하기', 'sb'); ?></h4>
                    <p class="sb-helper-text sb-helper-text-sm">
                        <?php _e('ChatGPT, Claude 등 AI에게 아래 프롬프트를 복사해서 붙여넣으면 안전하게 디자인을 변경할 수 있습니다:', 'sb'); ?>
                    </p>
                    <textarea readonly
                        class="sb-ai-prompt-area"><?php echo esc_textarea(SB_Helpers::get_ai_prompt_example()); ?></textarea>
                    <p class="sb-helper-text">
                        💡 <strong><?php _e('사용 방법', 'sb'); ?></strong>:
                        <?php _e('위 프롬프트를 복사 → 아래 "현재 템플릿" 복사해서 AI에게 함께 전달 → AI가 생성한 HTML을 아래 편집기에 붙여넣기', 'sb'); ?>
                    </p>
                </div>

                <form id="sb-template-form">
                    <div class="sb-template-group">
                        <label for="sb-redirect-template" class="sb-label-block">
                            <?php _e('리다이렉션 페이지 HTML 템플릿', 'sb'); ?>
                        </label>
                        <!-- CodeMirror 에디터가 여기에 초기화됩니다 -->
                        <textarea id="sb-redirect-template" name="redirect_template" rows="20" class="sb-template-editor codemirror-textarea"><?php
                        $current_template = get_option('sb_redirect_template', SB_Helpers::get_default_redirect_template());
                        echo esc_textarea($current_template);
                        ?></textarea>
                        <p class="description sb-desc-tight">
                            <?php _e('전체 HTML을 자유롭게 편집할 수 있습니다. CSS, JavaScript 포함 가능합니다.', 'sb'); ?>
                        </p>
                    </div>

                    <div id="sb-template-validation" class="sb-template-validation-box"></div>

                    <p class="submit sb-btn-group">
                        <button type="button" id="sb-validate-template" class="button">
                            ✓ <?php _e('템플릿 검증', 'sb'); ?>
                        </button>
                        <button type="submit" class="button button-primary" id="sb-save-template">
                            <?php _e('템플릿 저장', 'sb'); ?>
                        </button>
                        <button type="button" id="sb-reset-template" class="button">
                            <?php _e('기본값으로 복원', 'sb'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>

        <!-- 탭 5: 백업/복원 -->
        <div class="sb-tab-pane" id="tab-backup-restore">
            <div class="sb-settings-section">
                <h2><span class="dashicons dashicons-download"></span> <?php _e('백업 및 복원', 'sb'); ?></h2>
                <p class="description">
                    <?php _e('플러그인의 모든 데이터(링크, 통계, 설정)를 JSON 파일로 백업하거나 복원할 수 있습니다.', 'sb'); ?><br>
                    <strong><?php _e('주기적으로 백업하는 것을 권장합니다.', 'sb'); ?></strong>
                </p>

                <!-- 백업 다운로드 -->
                <div class="sb-backup-section">
                    <h4><?php _e('데이터 백업', 'sb'); ?></h4>
                    <button type="button" id="sb-download-backup" class="button button-secondary">
                        <span class="dashicons dashicons-download"></span>
                        <?php _e('백업 파일 다운로드 (.json)', 'sb'); ?>
                    </button>
                </div>

                <!-- 재난 복구 키트 (v3.4.0) -->
                <div class="sb-backup-section"
                    style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #2271b1;">
                    <h4 style="margin-top: 0;">
                        <span class="dashicons dashicons-shield" style="color: #2271b1;"></span>
                        <?php _e('재난 복구 키트 (Static HTML Backup)', 'sb'); ?>
                    </h4>
                    <p class="description">
                        <?php _e('모든 링크를 정적 HTML 파일로 변환하여 ZIP으로 다운로드합니다.', 'sb'); ?><br>
                        <?php _e('Github Pages, S3 등에 업로드하면 플러그인 없이도 링크가 작동합니다.', 'sb'); ?><br>
                        <strong><?php _e('중앙 수정 가능: sb-assets/loader.js 하나만 수정하면 전체 업데이트!', 'sb'); ?></strong>
                    </p>
                    <div style="margin-top: 15px;">
                        <button type="button" id="sb-generate-static-backup" class="button button-primary button-hero">
                            <span class="dashicons dashicons-database-export" style="margin-top: 5px;"></span>
                            <?php _e('정적 HTML 백업 생성', 'sb'); ?>
                        </button>
                    </div>
                    <div id="sb-static-backup-progress" style="display: none; margin-top: 15px;">
                        <div style="background: #e0e0e0; height: 20px; border-radius: 4px; overflow: hidden;">
                            <div id="sb-static-backup-bar"
                                style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s;"></div>
                        </div>
                        <p id="sb-static-backup-status" style="margin: 10px 0 0; color: #666;"></p>
                    </div>
                    <div id="sb-static-backup-result" style="display: none; margin-top: 15px;"></div>
                </div>

                <hr class="sb-divider">

                <!-- 백업 복원 -->
                <div>
                    <h4><?php _e('데이터 복원', 'sb'); ?></h4>
                    <p class="description sb-restore-desc">
                        <?php _e('주의: 복원 시 기존 설정과 데이터가 백업 파일의 내용으로 덮어씌워질 수 있습니다.', 'sb'); ?>
                    </p>
                    <form id="sb-restore-form" enctype="multipart/form-data">
                        <input type="file" id="sb-backup-file" name="backup_file" accept=".json" required>
                        <button type="submit" class="button button-primary">
                            <span class="dashicons dashicons-upload"></span>
                            <?php _e('복원 시작', 'sb'); ?>
                        </button>
                    </form>
                    <div id="sb-restore-progress" class="sb-restore-progress">
                        <span class="spinner is-active sb-spinner-inline"></span>
                        <?php _e('데이터 복원 중입니다... 페이지를 닫지 마세요.', 'sb'); ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- 탭 6: 공장 초기화 -->
        <div class="sb-tab-pane" id="tab-factory-reset">
            <div class="sb-danger-zone">
                <h3 class="sb-danger-title">
                    <span class="dashicons dashicons-warning sb-icon-warn"></span>
                    <?php _e('Danger Zone (위험 구역)', 'sb'); ?>
                </h3>
                <p class="sb-danger-text">
                    <?php _e('이 작업은 플러그인의 <strong>모든 데이터(링크, 통계 로그, API 키, 설정)</strong>를 영구적으로 삭제하고 초기 상태로 되돌립니다.', 'sb'); ?><br>
                    <span class="sb-text-danger"><?php _e('삭제된 데이터는 복구할 수 없습니다. 신중하게 진행해주세요.', 'sb'); ?></span>
                </p>
                <button type="button" id="sb-factory-reset" class="button button-primary sb-danger-btn">
                    <?php _e('Factory Reset (공장 초기화)', 'sb'); ?>
                </button>
            </div>
    
            <!-- 탭 7: 업데이트 및 롤백 (P3 기능 개선) -->
            <div class="sb-tab-pane" id="tab-update-rollback">
                <div class="sb-settings-section">
                    <h2><span class="dashicons dashicons-update"></span> <?php _e('업데이트 및 롤백', 'sb'); ?></h2>
                    <p class="description">
                        <?php _e('플러그인 업데이트 확인 및 롤백 기능을 제공합니다.', 'sb'); ?>
                    </p>
    
                    <!-- 업데이트 확인 섹션 -->
                    <div class="sb-update-section" style="margin-bottom: 30px;">
                        <h4><?php _e('업데이트 확인', 'sb'); ?></h4>
                        <div id="sb-update-status" style="margin: 15px 0;"></div>
                        
                        <div class="sb-update-actions">
                            <button type="button" id="sb-check-update" class="button button-secondary">
                                <span class="dashicons dashicons-search"></span>
                                <?php _e('업데이트 확인', 'sb'); ?>
                            </button>
                            <button type="button" id="sb-download-update" class="button button-primary" style="display: none;">
                                <span class="dashicons dashicons-download"></span>
                                <?php _e('업데이트 다운로드', 'sb'); ?>
                            </button>
                            <button type="button" id="sb-dismiss-update" class="button" style="display: none;">
                                <?php _e('알림 숨기기', 'sb'); ?>
                            </button>
                        </div>
    
                        <!-- 업데이트 로그 -->
                        <div style="margin-top: 20px;">
                            <h5><?php _e('업데이트 로그', 'sb'); ?></h5>
                            <div id="sb-update-logs" style="max-height: 200px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                                <p class="sb-muted"><?php _e('로그가 없습니다.', 'sb'); ?></p>
                            </div>
                            <button type="button" id="sb-clear-update-logs" class="button button-small" style="margin-top: 10px;">
                                <?php _e('로그 삭제', 'sb'); ?>
                            </button>
                        </div>
                    </div>
    
                    <hr class="sb-divider">
    
                    <!-- 롤백 섹션 -->
                    <div class="sb-rollback-section" style="margin-top: 30px;">
                        <h4><?php _e('롤백 관리', 'sb'); ?></h4>
                        <p class="description">
                            <?php _e('이전 백업 파일에서 데이터를 복원합니다. 롤백 전 자동으로 현재 데이터가 백업됩니다.', 'sb'); ?>
                        </p>
    
                        <!-- 롤백 백업 파일 목록 -->
                        <div style="margin: 15px 0;">
                            <h5><?php _e('사용 가능한 롤백 백업', 'sb'); ?></h5>
                            <div id="sb-rollback-backups-list" style="max-height: 250px; overflow-y: auto;">
                                <p class="sb-muted"><?php _e('백업 파일을 불러오는 중...', 'sb'); ?></p>
                            </div>
                        </div>
    
                        <!-- 롤백 로그 -->
                        <div style="margin-top: 20px;">
                            <h5><?php _e('롤백 로그', 'sb'); ?></h5>
                            <div id="sb-rollback-logs" style="max-height: 200px; overflow-y: auto; background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 12px;">
                                <p class="sb-muted"><?php _e('로그가 없습니다.', 'sb'); ?></p>
                            </div>
                            <button type="button" id="sb-clear-rollback-logs" class="button button-small" style="margin-top: 10px;">
                                <?php _e('로그 삭제', 'sb'); ?>
                            </button>
                        </div>
    
                        <!-- 자동 정리 설정 -->
                        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-left: 4px solid #2271b1;">
                            <h5 style="margin-top: 0;">
                                <span class="dashicons dashicons-trash"></span>
                                <?php _e('자동 정리', 'sb'); ?>
                            </h5>
                            <p class="description">
                                <?php _e('오래된 백업 파일을 자동으로 정리합니다.', 'sb'); ?>
                            </p>
                            <button type="button" id="sb-cleanup-rollback-backups" class="button button-secondary">
                                <?php _e('30일 이상 된 백업 정리', 'sb'); ?>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
    
        </div>
    </div>
</div>

<!-- 새 키 발급 모달 -->
<div id="sb-new-key-modal" class="sb-modal sb-hidden">
    <div class="sb-modal-overlay"></div>
    <div class="sb-modal-content">
        <h3><span class="dashicons dashicons-awards"></span> <?php _e('새 API 키가 발급되었습니다!', 'sb'); ?></h3>
        <p><strong><?php _e('아래 정보를 안전한 곳에 저장하세요. Secret Key는 다시 확인할 수 없습니다.', 'sb'); ?></strong></p>

        <div class="sb-key-display">
            <label><?php _e('API Key (공개 키)', 'sb'); ?></label>
            <div class="sb-key-row">
                <code id="sb-new-api-key"></code>
                <button type="button" class="button sb-copy-modal-key"
                    data-target="sb-new-api-key"><?php _e('복사', 'sb'); ?></button>
            </div>
        </div>

        <div class="sb-key-display">
            <label><?php _e('Secret Key (비밀 키) - ⚠️ 다시 확인 불가!', 'sb'); ?></label>
            <div class="sb-key-row">
                <code id="sb-new-secret-key"></code>
                <button type="button" class="button sb-copy-modal-key"
                    data-target="sb-new-secret-key"><?php _e('복사', 'sb'); ?></button>
            </div>
        </div>

        <div class="sb-modal-actions">
            <button type="button" class="button button-primary sb-close-modal"><?php _e('확인', 'sb'); ?></button>
        </div>
    </div>
</div>
