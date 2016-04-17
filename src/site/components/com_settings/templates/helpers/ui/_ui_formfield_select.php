<? defined('KOOWA') or die; ?>

<div class="control-group">
  <label class="control-label" for="<?= $id ?>">
    <?= $label ?>
  </label>

  <div class="controls">
    <select
      name="<?= $name ?>"
      class="<?= $class ?>"
      id="<?= $id ?>"
      <?= ($disabled) ? 'disabled' : '' ?>
    >
        <? foreach($options as $option) : ?>
        <option value="<?= $option['value'] ?>" <?= ($selected === $option['value']) ? 'selected' : '' ?>>
          <?= $option['name'] ?>
        </option>
        <? endforeach; ?>
    </select>
  </div>
</div>