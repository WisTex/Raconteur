### Nomadic Identity

One of the fundamental differences between Zot and other messaging systems/protocols is the support for nomadic identity. This simply means that your identity (who you are) is independent from the server you are posting from (where you are).

As a consequence, a person can have any number of active locations. Implementations which support nomadic identity MUST send a copy of all communications destined for that identity to all known active locations. If sites receive a communication from the given identity from any location, they MUST validate that the identity has authorised this location and (if verification is successful) deliver it appropriately. They SHOULD store the newly verified location and MAY subsequently used the stored information rather than re-validating.

When an identity modifies its location information, it MUST send a 'refresh' packet to all known sites where they maintain connections and which are capable of nomadic operation. The 'refresh' message informs the other site to perform network discovery and update all stored information related to the identity which may have changed.