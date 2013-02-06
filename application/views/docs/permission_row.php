<tr>
    <?php if($is_logged_in) : ?>
    <td><?php echo img(array('src' => 'assets/img/delete.png', 'class' => 'delete-item', 'id' => 'perm-'.$tid.'_'.$pid)); ?>
        <?php endif; ?>
    <td id="perm-<?php echo $tid.'_'.$pid.'-perm'; ?>" class="editable"><?php echo htmlspecialchars($perm); ?></td>
    <td id="perm-<?php echo $tid.'_'.$pid.'-pdesc'; ?>" class="editable"><?php echo htmlspecialchars($pdesc); ?></td>
</tr>
