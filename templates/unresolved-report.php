<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap treasury-portal-admin">
    <h1><?php esc_html_e( 'Unresolved Field Report', 'treasury-tech-portal' ); ?></h1>
    <?php if ( empty( $report ) ) : ?>
        <p><?php esc_html_e( 'No unresolved fields recorded.', 'treasury-tech-portal' ); ?></p>
    <?php else : ?>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Field', 'treasury-tech-portal' ); ?></th>
                    <th><?php esc_html_e( 'IDs', 'treasury-tech-portal' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $report as $field => $ids ) : ?>
                    <tr>
                        <td><?php echo esc_html( $field ); ?></td>
                        <td><?php echo esc_html( implode( ', ', (array) $ids ) ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
            <?php wp_nonce_field( 'ttp_download_unresolved', 'ttp_download_unresolved_nonce' ); ?>
            <input type="hidden" name="action" value="ttp_download_unresolved" />
            <?php submit_button( __( 'Download Report', 'treasury-tech-portal' ), 'secondary' ); ?>
        </form>
    <?php endif; ?>
</div>
