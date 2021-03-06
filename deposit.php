<?php
require_once 'util.php';
require_once 'voucher.php';

if (isset($_POST['amount']) && isset($_POST['curr_type']))
{
    if(isset($_POST['csrf_token']))
    {
        if($_SESSION['csrf_token'] != $_POST['csrf_token'])
        {
            throw new Error("csrf","csrf token mismatch!");
        }
    }
    else
    {
        throw new Error("csrf","csrf token missing");
    }
}

function show_bank_account_details($deposref)
{
    $deposref = format_deposref($deposref);
    if (strpos($deposref, ' ') !== FALSE) $deposref .= _(" (without spaces)");
?>
    <table class='display_data'>
        <tr>
            <td><?php echo _("Account title") . ":"; ?></td>
            <td><?php echo DEPOSIT_BANK_ACCOUNT_TITLE; ?></td>
        </tr>
        <tr>
            <td><?php echo _("Bank") . ":"; ?></td>
            <td><?php echo DEPOSIT_BANK_NAME; ?></td>
        </tr>
        <tr>
            <td><?php echo _("Account number") . ":"; ?></td>
            <td><?php echo DEPOSIT_BANK_ACCOUNT_NUMBER; ?></td>
        </tr>
        <tr>
            <td><?php echo _("BSB") . ":"; ?></td>
            <td><?php echo DEPOSIT_BANK_BRANCH_ID; ?></td>
        </tr>
        <tr>
            <td><?php echo _("Reference") . ":"; ?></td>
            <td><?php echo $deposref; ?></td>
        </tr>
    </table>
<?php
}

function show_deposit_voucher_form($code = '')
{ ?>
    <p>
        <form action='' class='indent_form' method='post'>
            <label for='input_code'><?php echo _("Voucher"); ?></label>
            <input class='voucher' type='text' onClick='select();' size='100' autocomplete='off' id='input_code' name='code' value='<?php echo $code; ?>' />
            <input type='hidden' name='csrf_token' value="<?php echo $_SESSION['csrf_token']; ?>" />
            <input type='submit' value='Submit' />
        </form>
    </p>
<?php
}

if (isset($_POST['code'])) {
    echo "<div class='content_box'>\n";
    echo "<h3>" . _("Deposit Voucher") . "</h3>\n";
    $code = post('code', '-');
    try {
        get_lock("redeem_voucher", 2);
        list ($curr_type, $amount) = redeem_voucher($code);
        echo ("<p><strong>" .
              sprintf(_("%s has been credited to your account."),
                      internal_to_numstr($amount) . " $curr_type") .
              "</strong></p>\n");
        echo "<p>" . _("got any more?") . "</p>\n";
        show_deposit_voucher_form($code);
    } catch (Exception $e) {
        $message = $e->getMessage();
        echo "<p>" . _("error") . ": $message</p>\n";
        echo "<p>" . _("try again?") . "</p>\n";
        show_deposit_voucher_form($code);
    }
    release_lock("redeem_voucher");
    echo "</div>\n";
} else {
    try {
        $addy = bitcoin_get_account_address((string)$is_logged_in);
    } catch (Exception $e) {
        if ($e->getMessage() != 'Unable to connect.')
            throw $e;
        $addy = '';
    }

    $query = "
        SELECT deposref
        FROM users
        WHERE uid='$is_logged_in';
    ";
    $result = do_query($query);
    $row = get_row($result);
    $deposref = $row['deposref'];
    $formatted_deposref = format_deposref($deposref);
?>

<div class='content_box'>
     <h3><?php echo _("Deposit Voucher"); ?></h3>
    <p><?php printf(
    _("It's possible to withdraw BTC or %s as 'vouchers' on the
       withdraw page.  These vouchers can be given to other exchange
       users and redeemed here."), CURRENCY); ?>
    </p>
    <p><?php printf(
    _("If you have received a voucher for this exchange, please
       copy/paste the voucher code into the box below to redeem it.")); ?>
    </p>
    <p><?php printf(
    _("We also accept %sMTGOX-%s-...%s vouchers for instant transfers
       of %s from MtGox to this exchange."), "<strong>", CURRENCY, "</strong>", CURRENCY); ?>
    <p><?php printf(
    _("Note that there will be a %s%% fee (capped at %s) taken for processing of MtGox vouchers."),
    COMMISSION_PERCENTAGE_FOR_DEPOSIT_MTGOX_FIAT_VOUCHER,
    sprintf("%.2f %s", COMMISSION_CAP_FOR_DEPOSIT_MTGOX_FIAT_VOUCHER, CURRENCY)); ?>
    </p>
<?php show_deposit_voucher_form(); ?>
</div>

<div class='content_box'>
    <h3><?php printf(_("Deposit %s by Bank Deposit (EFT)"), CURRENCY); ?></h3>
    <p><b><?php echo _("Depositing is free by bank deposit (EFT). You are responsible for paying any incurred fees. If your deposit is insufficient to cover bank fees then it will be denied."); ?></b></p>
<?php
    if ($is_verified) {
?>
    <p><?php printf(_("You will need to quote <strong>%s</strong> in the transaction's reference field."), $formatted_deposref); ?></p>
    <?php show_bank_account_details($deposref); ?>
    <p><?php echo _("Allow 3-5 working days for payments to pass through clearing."); ?></p>
    <p><b><?php echo _("Online Banking select your bank below to login."); ?></b></p>
    <p>
      <a target="_blank"
        href="https://www.my.commbank.com.au/netbank/Logon/Logon.aspx"
      >CBA</a>
      -
      <a target="_blank"
        href="https://www.anz.com/INETBANK/bankmain.asp"
      >ANZ</a>
      -
      <a target="_blank"
        href="https://online.westpac.com.au/esis/Login/SrvPage/?h3&app=wol&referrer=http%3A%2F%2Fwww.westpac.com.au%2FHomepageAlternative%2F"
      >WESTPAC</a>
      -
      <a target="_blank"
        href="https://ib.nab.com.au/nabib/index.jsp"
      >NAB</a>
      -
      <a target="_blank"
        href="http://www.google.com.au/"
      >Other</a>
    </p><br/>
<?php } else { ?>
    <p>If you plan to deposit via the Internet, we need to know about you.  Please <a href="?page=identity">identify yourself</a> here.</p>
<?php } ?>
    </div>
    <div class='content_box'>
    <h3><?php printf(_("Deposit %s Over The Counter"), CURRENCY); ?></h3>
    <strong><p>24 hour clearing visit any Commonwealth Bank Australia to deposit funds over the counter.<p>WARNING! please do not deposit funds via the internet using this method without "VERIFICATION", your (AUD) funds will be rejected or seized for fraud investigation.</p></strong>
    <?php  ?>
    <p>
<?php
    if (ctype_digit($deposref)) {
        printf(_("Please use your unique reference number <strong>%s</strong> so we know which account to credit."), $formatted_deposref);
        $ref = $deposref;
    } else {
        printf(_("Please use your User ID <strong>%s</strong> as the reference so we know which account to credit."), $is_logged_in);
        $ref = $is_logged_in;
    }
    show_bank_account_details($ref);
?>
</p>
</div>

<div class='content_box'>
    <h3><?php echo _("Deposit"); ?> BTC</h3>
<?php
    if ($addy) {
        echo "    <p>" . sprintf(_("You can deposit to %s"), "<b>$addy</b>") . "</p>\n";
        echo "    <p>" . _("The above address is specific to your account.  Each time you deposit, a new address will be generated for you.") . "</p>\n";
        echo "    <p>" . sprintf(_("It takes %s confirmations before funds are added to your account."), CONFIRMATIONS_FOR_DEPOSIT) . "</p>\n";
        if (!$is_verified)
            echo "    <p>Note that you will be able to deposit BTC and trade them back and forth for AUD, but until you <a href=\"?page=identity\">identify yourself</a>, you will be unable to make any withdrawls.</p>\n";
    } else
        echo "    <p>" . _("We are currently experiencing trouble connecting to the Bitcoin network.  Please try again in a few minutes.") . "</p>\n";
    echo "</div>\n";
}
