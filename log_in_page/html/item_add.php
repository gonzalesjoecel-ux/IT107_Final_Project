    <?php
    /** @var PDO $pdo   (from db.php via includes/auth.php) */
    /** @var array $user (from includes/auth.php) */
    /* item_add.php — saves a new item with optional picture/document (handler only, no HTML) */
    require __DIR__ . '/includes/auth.php';
    require __DIR__ . '/includes/crypto.php';
    require __DIR__ . '/includes/upload.php';
    require_once __DIR__ . '/includes/activity.php';

    const MAX_ITEMS = 100;

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        redirect('user_dashboard.php');
    }

    /* If the upload is bigger than PHP's post_max_size, PHP empties $_POST and $_FILES */
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
        flash('err', 'The uploaded file is too large. Maximum file size is 5 MB.');
        redirect('user_dashboard.php');
    }

    if (!csrf_check($_POST['csrf_token'] ?? '')) {
        flash('err', 'The form has expired. Please try again.');
        redirect('user_dashboard.php');
    }

    $title   = trim($_POST['title'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $hasFile = has_upload();

    if ($title === '') {
        flash('err', 'Please enter a title.');
        redirect('user_dashboard.php');
    }
    if ($content === '' && !$hasFile) {
        flash('err', 'Enter some content or attach a picture/document.');
        redirect('user_dashboard.php');
    }
    if (mb_strlen($title) > 120) {
        flash('err', 'The title is too long (maximum 120 characters).');
        redirect('user_dashboard.php');
    }
    if (mb_strlen($content) > 5000) {
        flash('err', 'The content is too long (maximum 5000 characters).');
        redirect('user_dashboard.php');
    }

    $file = null; // info of the saved file (if any)

    try {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM items WHERE user_id = ?');
        $stmt->execute([$user['id']]);

        if ((int)$stmt->fetchColumn() >= MAX_ITEMS) {
            flash('err', 'You have reached the limit of ' . MAX_ITEMS . ' items.');
            redirect('user_dashboard.php');
        }

        if ($hasFile) {
            $file = save_upload((int)$user['id']);
        }

        $encrypted = encrypt_text($content, 'user:' . $user['id']);

        $stmt = $pdo->prepare(
            'INSERT INTO items (user_id, title, content_enc, file_name, file_stored, file_mime, file_size)
            VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $user['id'],
            $title,
            $encrypted,
            $file['name']   ?? null,
            $file['stored'] ?? null,
            $file['mime']   ?? null,
            $file['size']   ?? null,
        ]);

        flash('ok', 'Item saved successfully.');
        log_event($pdo, (int)$user['id'], 'item_add', $file ? 'with file' : '');

    } catch (UploadException $ex) {
        flash('err', $ex->getMessage());

    } catch (Throwable $ex) {
        /* DB failed after the file was saved: don't leave an orphan file */
        if ($file) {
            @unlink(stored_file_path($file['stored']));
        }
        error_log('item_add: ' . $ex->getMessage());
        flash('err', 'There was a problem saving the item. Make sure items_setup.sql and items_upload_update.sql have been run.');
    }

    redirect('user_dashboard.php');