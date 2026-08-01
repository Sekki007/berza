package rs.kupitelefon.app;

import android.content.Intent;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.View;
import android.webkit.CookieManager;
import android.webkit.WebView;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    private static final String SITE_ORIGIN = "https://kupitelefon.rs";

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        WindowCompat.setDecorFitsSystemWindows(getWindow(), true);
        getWindow().setStatusBarColor(Color.WHITE);
        getWindow().setNavigationBarColor(Color.parseColor("#F2F2F2"));
        WindowInsetsControllerCompat insets = WindowCompat.getInsetsController(getWindow(), getWindow().getDecorView());
        if (insets != null) {
            insets.setAppearanceLightStatusBars(true);
            insets.setAppearanceLightNavigationBars(true);
        }

        CookieManager cookies = CookieManager.getInstance();
        cookies.setAcceptCookie(true);
        if (this.getBridge() != null && this.getBridge().getWebView() != null) {
            cookies.setAcceptThirdPartyCookies(this.getBridge().getWebView(), true);
            this.getBridge().getWebView().setOverScrollMode(View.OVER_SCROLL_NEVER);
            this.getBridge().getWebView().setBackgroundColor(Color.parseColor("#F2F2F2"));
        }

        // Sačekaj WebView pa otvori link iz notifikacije (cold start)
        new Handler(Looper.getMainLooper()).postDelayed(() -> openLinkFromIntent(getIntent()), 700);
    }

    @Override
    protected void onNewIntent(Intent intent) {
        super.onNewIntent(intent);
        setIntent(intent);
        openLinkFromIntent(intent);
    }

    private void openLinkFromIntent(Intent intent) {
        if (intent == null) {
            return;
        }
        String link = null;
        if (intent.getExtras() != null) {
            link = intent.getExtras().getString("link");
            if (link == null || link.isEmpty()) {
                link = intent.getExtras().getString("click_action");
            }
        }
        if (link == null || link.isEmpty()) {
            Uri data = intent.getData();
            if (data != null) {
                link = data.toString();
            }
        }
        if (link == null || link.isEmpty()) {
            return;
        }
        // FCM_PLUGIN_ACTIVITY nije URL — ignoriši
        if ("FCM_PLUGIN_ACTIVITY".equals(link)) {
            link = SITE_ORIGIN + "/poruke.php";
        }
        if (link.startsWith("/")) {
            link = SITE_ORIGIN + link;
        }
        if (!link.startsWith("https://kupitelefon.rs") && !link.startsWith("http://kupitelefon.rs")) {
            return;
        }

        final String finalLink = link;
        WebView webView = (this.getBridge() != null) ? this.getBridge().getWebView() : null;
        if (webView == null) {
            new Handler(Looper.getMainLooper()).postDelayed(() -> {
                WebView wv = (this.getBridge() != null) ? this.getBridge().getWebView() : null;
                if (wv != null) {
                    wv.loadUrl(finalLink);
                }
            }, 900);
            return;
        }
        webView.loadUrl(finalLink);
    }

    @Override
    public void onPause() {
        CookieManager.getInstance().flush();
        super.onPause();
    }

    @Override
    public void onStop() {
        CookieManager.getInstance().flush();
        super.onStop();
    }
}
