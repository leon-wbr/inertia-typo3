import "@inertiajs/core";

export interface NavigationItem {
  uid: number;
  title: string;
  link: string;
  active: boolean;
  current: boolean;
  children: NavigationItem[];
}

export interface SiteLanguage {
  id: number;
  title: string;
  code: string;
  link: string;
  active: boolean;
}

export interface ContentElement {
  CType: string;
  data: Record<string, unknown>;
  html: string | null;
}

declare module "@inertiajs/core" {
  export interface InertiaConfig {
    sharedPageProps: {
      site: {
        title: string;
        home: string;
        language: string;
        languages: SiteLanguage[];
      };
      navigation: NavigationItem[];
    };
  }
}
