import { Party } from "./party";

declare module "@inertiajs/core" {
  export interface InertiaConfig {
    sharedPageProps: {
      auth: { user: { id: number; name: string } | null };
      parties: Party[];
      selected_party?: Party;
      appName: string;
    };
  }
}