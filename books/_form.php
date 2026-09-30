<form method="post" class="panel-body">
    <?= csrfField() ?>
    <div class="form-grid cols-2">
        <div class="field">
            <label class="label" for="book_number">Book number</label>
            <input class="input is-code" type="text" id="book_number" name="book_number" value="<?= e($form['book_number']) ?>" maxlength="20" required autocomplete="off">
            <span class="hint">The accession number printed on the book.</span>
        </div>
        <div class="field">
            <label class="label" for="title">Title</label>
            <input class="input" type="text" id="title" name="title" value="<?= e($form['title']) ?>" maxlength="500" required>
        </div>
        <div class="field span-all">
            <label class="label" for="author">Author</label>
            <input class="input" type="text" id="author" name="author" value="<?= e($form['author']) ?>" maxlength="255" required>
            <span class="hint">Last name first, for example Glenn, Paul J.</span>
        </div>
    </div>
    <div class="form-grid cols-3">
        <div class="field">
            <label class="label" for="copyright_year">Copyright year <span class="opt">(optional)</span></label>
            <input class="input" type="text" id="copyright_year" name="copyright_year" value="<?= e($form['copyright_year']) ?>" maxlength="10" inputmode="numeric">
        </div>
        <div class="field">
            <label class="label" for="edition">Edition <span class="opt">(optional)</span></label>
            <input class="input" type="text" id="edition" name="edition" value="<?= e($form['edition']) ?>" maxlength="50">
        </div>
        <div class="field">
            <label class="label" for="course_code">Course code <span class="opt">(optional)</span></label>
            <input class="input" type="text" id="course_code" name="course_code" value="<?= e($form['course_code']) ?>" maxlength="50">
        </div>
    </div>
    <div class="actions">
        <button type="submit" class="btn btn-primary"<?= !empty($locked) ? ' disabled' : '' ?>><?= e($submitLabel) ?></button>
        <a class="btn btn-quiet" href="<?= e(url('books/list.php')) ?>">Cancel</a>
    </div>
</form>
