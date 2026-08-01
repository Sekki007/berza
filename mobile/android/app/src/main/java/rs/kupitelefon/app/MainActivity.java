package rs.kupitelefon.app;

import android.graphics.Color;
import android.os.Bundle;
import android.view.View;
import android.webkit.CookieManager;
import androidx.core.view.WindowCompat;
import androidx.core.view.WindowInsetsControllerCompat;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {
    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Edge-to-edge feel without covering content awkwardly
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
